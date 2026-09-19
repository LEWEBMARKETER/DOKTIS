<?php

namespace App\Http\Controllers\Api;

use App\Events\RendezVousMisAJour;
use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Services\DisponibiliteRendezVous;
use App\Support\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RendezVousController extends Controller
{
    public function __construct(private readonly DisponibiliteRendezVous $disponibilite) {}

    public function index(Request $request)
    {
        $query = RendezVous::query()->with(['patient', 'praticien']);

        if ($debut = $request->query('debut')) {
            $query->where('debut', '>=', $debut);
        }

        if ($fin = $request->query('fin')) {
            $query->where('fin', '<=', $fin);
        }

        if ($praticienId = $request->query('praticien_id')) {
            $query->where('praticien_id', $praticienId);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->orderBy('debut')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', TenantRule::exists($request->user(), 'patients')],
            'praticien_id' => ['required', TenantRule::exists($request->user(), 'users')->where('actif', true)],
            'motif' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:consultation,controle,urgence,soin'],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'notes' => ['nullable', 'string'],
        ]);

        $rendezVous = DB::transaction(function () use ($data, $request) {
            abort_if($this->disponibilite->conflit($data['praticien_id'], $data['debut'], $data['fin']), 422,
                'Ce créneau chevauche un autre rendez-vous du praticien.');

            return RendezVous::create([...$data, 'created_by' => $request->user()->id]);
        });

        broadcast(new RendezVousMisAJour($rendezVous));

        return response()->json($rendezVous->load(['patient', 'praticien']), 201);
    }

    public function show(RendezVous $rendezVous)
    {
        return $rendezVous->load(['patient', 'praticien', 'creePar']);
    }

    public function update(Request $request, RendezVous $rendezVous)
    {
        $data = $request->validate([
            'patient_id' => ['sometimes', TenantRule::exists($request->user(), 'patients')],
            'praticien_id' => ['sometimes', TenantRule::exists($request->user(), 'users')->where('actif', true)],
            'motif' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:consultation,controle,urgence,soin'],
            'debut' => ['sometimes', 'date'],
            'fin' => ['sometimes', 'date', 'after:debut'],
            'statut' => ['sometimes', 'in:planifie,confirme,reprogramme,termine,annule,absent'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $rendezVous) {
            $praticienId = $data['praticien_id'] ?? $rendezVous->praticien_id;
            $debut = $data['debut'] ?? $rendezVous->debut->toISOString();
            $fin = $data['fin'] ?? $rendezVous->fin->toISOString();
            abort_if($this->disponibilite->conflit($praticienId, $debut, $fin, $rendezVous->id), 422,
                'Ce créneau chevauche un autre rendez-vous du praticien.');
            $rendezVous->update($data);
        });

        broadcast(new RendezVousMisAJour($rendezVous));

        return response()->json($rendezVous->load(['patient', 'praticien']));
    }

    public function destroy(RendezVous $rendezVous)
    {
        $rendezVous->delete();

        return response()->json(status: 204);
    }

    /**
     * Reprogramme un rendez-vous : conserve l'ancien (statut "reprogramme") et
     * crée le nouveau créneau, pour garder l'historique visible côté patient.
     */
    public function reprogrammer(Request $request, RendezVous $rendezVous)
    {
        $data = $request->validate([
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
        ]);

        $nouveau = DB::transaction(function () use ($rendezVous, $data, $request) {
            abort_if($this->disponibilite->conflit($rendezVous->praticien_id, $data['debut'], $data['fin'], $rendezVous->id), 422,
                'Ce créneau chevauche un autre rendez-vous du praticien.');
            $rendezVous->update(['statut' => 'reprogramme']);

            return RendezVous::create([
                'cabinet_id' => $rendezVous->cabinet_id,
                'patient_id' => $rendezVous->patient_id,
                'praticien_id' => $rendezVous->praticien_id,
                'motif' => $rendezVous->motif,
                'type' => $rendezVous->type,
                'source' => $rendezVous->source,
                'reprogramme_depuis_id' => $rendezVous->id,
                'debut' => $data['debut'],
                'fin' => $data['fin'],
                'statut' => 'planifie',
                'created_by' => $request->user()->id,
            ]);
        });

        broadcast(new RendezVousMisAJour($nouveau));

        return response()->json($nouveau->load(['patient', 'praticien']), 201);
    }
}
