<?php

namespace App\Http\Controllers\Api;

use App\Events\RendezVousMisAJour;
use App\Http\Controllers\Api\Concerns\PrevientChevauchementRendezVous;
use App\Http\Controllers\Controller;
use App\Models\JournalAction;
use App\Models\RendezVous;
use Illuminate\Http\Request;

class RendezVousController extends Controller
{
    use PrevientChevauchementRendezVous;

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
            'patient_id' => ['required', 'exists:patients,id'],
            'praticien_id' => ['required', 'exists:users,id'],
            'motif' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:consultation,controle,urgence,soin'],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'notes' => ['nullable', 'string'],
        ]);

        // Vérification préalable : donne un message clair dans le cas
        // courant (non concurrent). La protection réelle contre le
        // double-booking est la contrainte EXCLUDE PostgreSQL, appliquée
        // par creerSansChevauchement() au moment de l'écriture.
        $conflit = RendezVous::where('praticien_id', $data['praticien_id'])
            ->where('statut', '!=', 'annule')
            ->where('debut', '<', $data['fin'])
            ->where('fin', '>', $data['debut'])
            ->exists();

        if ($conflit) {
            return response()->json([
                'message' => 'Ce créneau chevauche un autre rendez-vous du praticien.',
            ], 422);
        }

        $rendezVous = $this->creerSansChevauchement(function () use ($data, $request) {
            return RendezVous::create([
                ...$data,
                'created_by' => $request->user()->id,
            ]);
        });

        JournalAction::enregistrer('rendez_vous.cree', RendezVous::class, $rendezVous->id, ['patient_id' => $rendezVous->patient_id]);

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
            'patient_id' => ['sometimes', 'exists:patients,id'],
            'praticien_id' => ['sometimes', 'exists:users,id'],
            'motif' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:consultation,controle,urgence,soin'],
            'debut' => ['sometimes', 'date'],
            'fin' => ['sometimes', 'date', 'after:debut'],
            'statut' => ['sometimes', 'in:planifie,confirme,reprogramme,termine,annule,absent'],
            'notes' => ['nullable', 'string'],
        ]);

        $rendezVous->update($data);

        if (($data['statut'] ?? null) === 'annule') {
            JournalAction::enregistrer('rendez_vous.annule', RendezVous::class, $rendezVous->id, ['patient_id' => $rendezVous->patient_id]);
        }

        broadcast(new RendezVousMisAJour($rendezVous));

        return response()->json($rendezVous->load(['patient', 'praticien']));
    }

    public function destroy(RendezVous $rendezVous)
    {
        JournalAction::enregistrer('rendez_vous.supprime', RendezVous::class, $rendezVous->id, ['patient_id' => $rendezVous->patient_id]);

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

        $conflit = RendezVous::where('praticien_id', $rendezVous->praticien_id)
            ->where('id', '!=', $rendezVous->id)
            ->where('statut', '!=', 'annule')
            ->where('debut', '<', $data['fin'])
            ->where('fin', '>', $data['debut'])
            ->exists();

        if ($conflit) {
            return response()->json(['message' => 'Ce créneau chevauche un autre rendez-vous du praticien.'], 422);
        }

        $nouveau = $this->creerSansChevauchement(function () use ($rendezVous, $data, $request) {
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

        JournalAction::enregistrer('rendez_vous.reprogramme', RendezVous::class, $nouveau->id, ['depuis' => $rendezVous->id]);

        broadcast(new RendezVousMisAJour($nouveau));

        return response()->json($nouveau->load(['patient', 'praticien']), 201);
    }
}
