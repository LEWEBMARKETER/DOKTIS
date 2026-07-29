<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $query = Consultation::query()->with(['patient', 'praticien']);

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return $query->orderByDesc('date_consultation')->paginate($request->integer('par_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'praticien_id' => ['required', 'exists:users,id'],
            'rendez_vous_id' => ['nullable', 'exists:rendez_vous,id'],
            'date_consultation' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
            'diagnostic' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'traitement' => ['nullable', 'string'],
            'poids' => ['nullable', 'numeric'],
            'tension' => ['nullable', 'string', 'max:20'],
            'temperature' => ['nullable', 'numeric'],
            'prochaine_visite_recommandee' => ['nullable', 'date'],
        ]);

        $consultation = Consultation::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        if (! empty($data['rendez_vous_id'])) {
            $consultation->rendezVous()->update(['statut' => 'termine']);
        }

        return response()->json($consultation->load(['patient', 'praticien']), 201);
    }

    public function show(Consultation $consultation)
    {
        return $consultation->load(['patient', 'praticien', 'documents', 'ordonnances']);
    }

    public function update(Request $request, Consultation $consultation)
    {
        $data = $request->validate([
            'motif' => ['nullable', 'string', 'max:255'],
            'diagnostic' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'traitement' => ['nullable', 'string'],
            'poids' => ['nullable', 'numeric'],
            'tension' => ['nullable', 'string', 'max:20'],
            'temperature' => ['nullable', 'numeric'],
            'prochaine_visite_recommandee' => ['nullable', 'date'],
        ]);

        $consultation->update($data);

        return response()->json($consultation);
    }

    public function destroy(Consultation $consultation)
    {
        $consultation->delete();

        return response()->json(status: 204);
    }
}
