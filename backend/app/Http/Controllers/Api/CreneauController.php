<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Créneaux de la page de réservation, gérés par l'équipe du cabinet.
 * Dates et heures sont échangées en heure locale.
 */
class CreneauController extends Controller
{
    /** Garde-fou : nombre maximal de créneaux créés en une fois. */
    private const MAX_PAR_GENERATION = 1000;

    public function index(Request $request)
    {
        $this->cabinetId($request);
        $fuseau = config('dokta.fuseau_horaire_local');

        $data = $request->validate([
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);

        $du = Carbon::createFromFormat('Y-m-d', $data['du'] ?? now($fuseau)->toDateString(), $fuseau)->startOfDay();
        $au = Carbon::createFromFormat('Y-m-d', $data['au'] ?? $du->copy()->addDays(13)->toDateString(), $fuseau)->endOfDay();
        abort_if($du->diffInDays($au) > 62, 422, 'Choisissez une période de deux mois au plus.');

        return Creneau::with('praticien:id,name')
            ->whereBetween('debut', [$du->utc(), $au->utc()])
            ->orderBy('debut')
            ->get();
    }

    /**
     * Crée en une fois les créneaux d'une période : chaque jour retenu, de
     * l'heure de début à l'heure de fin, par pas de « duree_minutes ».
     * Les créneaux passés ou en conflit avec un créneau existant sont ignorés.
     */
    public function generer(Request $request)
    {
        $cabinetId = $this->cabinetId($request);
        $fuseau = config('dokta.fuseau_horaire_local');

        $data = $request->validate([
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'jours_semaine' => ['required', 'array', 'min:1'],
            'jours_semaine.*' => ['integer', 'min:0', 'max:6', 'distinct'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'duree_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'praticien_id' => ['nullable', 'integer'],
        ]);

        $debutPeriode = Carbon::createFromFormat('Y-m-d', $data['date_debut'], $fuseau)->startOfDay();
        $finPeriode = Carbon::createFromFormat('Y-m-d', $data['date_fin'], $fuseau)->startOfDay();
        abort_if($debutPeriode->diffInDays($finPeriode) > 62, 422, 'Choisissez une période de deux mois au plus.');

        $praticienId = $data['praticien_id'] ?? null;
        if ($praticienId) {
            $valide = User::where('id', $praticienId)
                ->where('cabinet_id', $cabinetId)
                ->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
                ->exists();
            abort_unless($valide, 422, "Ce praticien n'appartient pas à votre cabinet.");
        }

        $candidats = [];
        for ($jour = $debutPeriode->copy(); $jour->lte($finPeriode); $jour->addDay()) {
            if (! in_array($jour->dayOfWeek, $data['jours_semaine'], true)) {
                continue;
            }

            $curseur = Carbon::createFromFormat('Y-m-d H:i', $jour->toDateString().' '.$data['heure_debut'], $fuseau);
            $limite = Carbon::createFromFormat('Y-m-d H:i', $jour->toDateString().' '.$data['heure_fin'], $fuseau);

            while ($curseur->copy()->addMinutes($data['duree_minutes'])->lte($limite)) {
                $fin = $curseur->copy()->addMinutes($data['duree_minutes']);
                if ($curseur->isFuture()) {
                    $candidats[] = [$curseur->copy()->utc(), $fin->copy()->utc()];
                }
                $curseur = $fin;
            }
        }

        abort_if(count($candidats) > self::MAX_PAR_GENERATION, 422, 'Trop de créneaux d\'un coup : réduisez la période ou allongez la durée.');

        $existants = Creneau::query()
            ->when($praticienId, fn ($q) => $q->where(fn ($q) => $q->where('praticien_id', $praticienId)->orWhereNull('praticien_id')))
            ->where('debut', '<', $finPeriode->copy()->addDays(2)->utc())
            ->where('fin', '>', $debutPeriode->copy()->utc())
            ->get(['debut', 'fin']);

        $aCreer = array_filter($candidats, function (array $c) use ($existants) {
            [$debut, $fin] = $c;

            return ! $existants->contains(fn (Creneau $e) => $e->debut->lt($fin) && $e->fin->gt($debut));
        });

        $maintenant = now();
        DB::table('creneaux')->insert(array_map(fn (array $c) => [
            'cabinet_id' => $cabinetId,
            'praticien_id' => $praticienId,
            'debut' => $c[0],
            'fin' => $c[1],
            'statut' => 'libre',
            'demandes' => 0,
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ], array_values($aCreer)));

        return response()->json([
            'crees' => count($aCreer),
            'ignores' => count($candidats) - count($aCreer),
        ], 201);
    }

    public function update(Request $request, Creneau $creneau)
    {
        $this->cabinetId($request);
        $data = $request->validate(['statut' => ['required', Rule::in(['libre', 'pris'])]]);

        $creneau->update($data);

        return response()->json($creneau->load('praticien:id,name'));
    }

    public function destroy(Request $request, Creneau $creneau)
    {
        $this->cabinetId($request);
        $creneau->delete();

        return response()->noContent();
    }

    /**
     * Supprime les créneaux encore libres d'une journée (jour de fermeture imprévu).
     */
    public function viderJour(Request $request, string $date)
    {
        $this->cabinetId($request);
        $fuseau = config('dokta.fuseau_horaire_local');
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 422, 'Date invalide.');

        $jour = Carbon::createFromFormat('Y-m-d', $date, $fuseau);
        $supprimes = Creneau::where('statut', 'libre')
            ->whereBetween('debut', [$jour->copy()->startOfDay()->utc(), $jour->copy()->endOfDay()->utc()])
            ->delete();

        return response()->json(['supprimes' => $supprimes]);
    }

    private function cabinetId(Request $request): int
    {
        $cabinetId = $request->user()->cabinet_id;
        abort_unless($cabinetId, 404);

        return $cabinetId;
    }
}
