<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ordonnance;
use Illuminate\Http\Request;

class OrdonnanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Ordonnance::query()->with(['patient', 'praticien']);

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return $query->latest('date_emission')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'exists:consultations,id'],
            'ordonnance_modele_id' => ['nullable', 'exists:ordonnance_modeles,id'],
            'contenu' => ['required', 'string'],
            'date_emission' => ['required', 'date'],
        ]);

        $ordonnance = Ordonnance::create([
            ...$data,
            'praticien_id' => $request->user()->id,
        ]);

        return response()->json($ordonnance->load(['patient', 'praticien']), 201);
    }

    public function show(Ordonnance $ordonnance)
    {
        return $ordonnance->load(['patient', 'praticien', 'consultation']);
    }

    public function destroy(Ordonnance $ordonnance)
    {
        $ordonnance->delete();

        return response()->json(status: 204);
    }
}
