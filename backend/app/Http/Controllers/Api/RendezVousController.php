<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use Illuminate\Http\Request;

class RendezVousController extends Controller
{
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

        $rendezVous = RendezVous::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

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
            'statut' => ['sometimes', 'in:planifie,confirme,termine,annule,absent'],
            'notes' => ['nullable', 'string'],
        ]);

        $rendezVous->update($data);

        return response()->json($rendezVous->load(['patient', 'praticien']));
    }

    public function destroy(RendezVous $rendezVous)
    {
        $rendezVous->delete();

        return response()->json(status: 204);
    }
}
