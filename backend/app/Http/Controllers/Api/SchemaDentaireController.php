<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DentTraitement;
use App\Models\Patient;
use Illuminate\Http\Request;

class SchemaDentaireController extends Controller
{
    /**
     * Numérotation FDI des 32 dents permanentes (4 quadrants de 8 dents).
     */
    private function numerosDents(): array
    {
        $numeros = [];
        foreach ([1, 2, 3, 4] as $quadrant) {
            for ($dent = 1; $dent <= 8; $dent++) {
                $numeros[] = ($quadrant * 10) + $dent;
            }
        }

        return $numeros;
    }

    public function index(Patient $patient)
    {
        $dernieres = DentTraitement::where('patient_id', $patient->id)
            ->orderByDesc('date_traitement')
            ->orderByDesc('id')
            ->get()
            ->unique('numero_dent')
            ->keyBy('numero_dent');

        $schema = collect($this->numerosDents())->map(function (int $numero) use ($dernieres) {
            $entree = $dernieres->get($numero);

            return [
                'numero_dent' => $numero,
                'statut' => $entree->statut ?? 'sain',
                'type_traitement' => $entree->type_traitement ?? null,
                'date_traitement' => $entree->date_traitement ?? null,
                'derniere_entree_id' => $entree->id ?? null,
            ];
        });

        return response()->json($schema);
    }

    public function historique(Patient $patient, Request $request)
    {
        $query = DentTraitement::where('patient_id', $patient->id)->with('praticien:id,name');

        if ($numero = $request->query('numero_dent')) {
            $query->where('numero_dent', $numero);
        }

        return $query->orderByDesc('date_traitement')->get();
    }

    public function store(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'numero_dent' => ['required', 'integer', 'min:11', 'max:48'],
            'type_traitement' => ['required', 'string', 'max:100'],
            'statut' => ['required', 'in:sain,a_traiter,traite,absent'],
            'consultation_id' => ['nullable', 'exists:consultations,id'],
            'plan_traitement_id' => ['nullable', 'exists:plans_traitement,id'],
            'date_traitement' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_if($data['numero_dent'] % 10 === 0 || $data['numero_dent'] % 10 === 9, 422, 'Numéro de dent FDI invalide.');

        $entree = DentTraitement::create([
            ...$data,
            'patient_id' => $patient->id,
            'praticien_id' => $request->user()->id,
        ]);

        return response()->json($entree, 201);
    }
}
