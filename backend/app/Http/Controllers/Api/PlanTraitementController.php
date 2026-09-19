<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanTraitement;
use App\Support\TenantRule;
use Illuminate\Http\Request;

class PlanTraitementController extends Controller
{
    public function index(Request $request)
    {
        $query = PlanTraitement::query()->with(['patient', 'praticien', 'etapes']);

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return $query->latest()->paginate($request->integer('par_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', TenantRule::exists($request->user(), 'patients')],
            'praticien_id' => ['required', TenantRule::exists($request->user(), 'users')->where('actif', true)],
            'consultation_id' => ['nullable', TenantRule::exists($request->user(), 'consultations')],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cout_estime' => ['nullable', 'numeric'],
            'date_debut' => ['nullable', 'date'],
            'date_fin_prevue' => ['nullable', 'date'],
            'etapes' => ['nullable', 'array'],
            'etapes.*.titre' => ['required_with:etapes', 'string', 'max:255'],
            'etapes.*.description' => ['nullable', 'string'],
            'etapes.*.cout' => ['nullable', 'numeric'],
            'etapes.*.date_prevue' => ['nullable', 'date'],
        ]);

        $plan = PlanTraitement::create([
            ...collect($data)->except('etapes')->all(),
        ]);

        foreach ($data['etapes'] ?? [] as $ordre => $etape) {
            $plan->etapes()->create([...$etape, 'ordre' => $ordre]);
        }

        return response()->json($plan->load('etapes'), 201);
    }

    public function show(PlanTraitement $planTraitement)
    {
        return $planTraitement->load(['patient', 'praticien', 'etapes']);
    }

    public function update(Request $request, PlanTraitement $planTraitement)
    {
        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'statut' => ['sometimes', 'in:propose,en_cours,termine,abandonne'],
            'cout_estime' => ['nullable', 'numeric'],
            'date_debut' => ['nullable', 'date'],
            'date_fin_prevue' => ['nullable', 'date'],
        ]);

        $planTraitement->update($data);

        return response()->json($planTraitement->load('etapes'));
    }

    public function destroy(PlanTraitement $planTraitement)
    {
        $planTraitement->delete();

        return response()->json(status: 204);
    }

    public function majEtape(Request $request, PlanTraitement $planTraitement, int $etape)
    {
        $ligne = $planTraitement->etapes()->findOrFail($etape);

        $data = $request->validate([
            'statut' => ['sometimes', 'in:a_faire,en_cours,realisee,annulee'],
            'date_realisee' => ['nullable', 'date'],
        ]);

        $ligne->update($data);

        return response()->json($ligne);
    }
}
