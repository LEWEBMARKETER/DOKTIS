<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()->where('actif', true);

        if ($search = $request->query('recherche')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenom', 'ilike', "%{$search}%")
                    ->orWhere('numero_dossier', 'ilike', "%{$search}%")
                    ->orWhere('telephone', 'ilike', "%{$search}%");
            });
        }

        return $query->orderBy('nom')->paginate($request->integer('par_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'in:M,F'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string'],
            'antecedents_medicaux' => ['nullable', 'string'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $patient = Patient::create([
            ...$data,
            'numero_dossier' => $this->genererNumeroDossier($request),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($patient, 201);
    }

    public function show(Patient $patient)
    {
        return $patient->load(['rendezVous' => fn ($q) => $q->latest('debut')->limit(5), 'consultations' => fn ($q) => $q->latest('date_consultation')->limit(5)]);
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'in:M,F'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string'],
            'antecedents_medicaux' => ['nullable', 'string'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $patient->update($data);

        return response()->json($patient);
    }

    public function destroy(Patient $patient)
    {
        $patient->update(['actif' => false]);
        $patient->delete();

        return response()->json(status: 204);
    }

    private function genererNumeroDossier(Request $request): string
    {
        $cabinetId = $request->user()->cabinet_id;
        $annee = now()->format('Y');
        $sequence = Patient::withTrashed()->where('cabinet_id', $cabinetId)->count() + 1;

        return sprintf('%s-%04d', $annee, $sequence).'-'.Str::upper(Str::random(3));
    }
}
