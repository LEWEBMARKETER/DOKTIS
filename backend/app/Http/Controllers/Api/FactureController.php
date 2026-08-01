<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactureController extends Controller
{
    public function index(Request $request)
    {
        $query = Facture::query()->with(['patient']);

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest('date_emission')->paginate($request->integer('par_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'exists:consultations,id'],
            'date_emission' => ['required', 'date'],
            'date_echeance' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $facture = DB::transaction(function () use ($data, $request) {
            $facture = Facture::create([
                'patient_id' => $data['patient_id'],
                'consultation_id' => $data['consultation_id'] ?? null,
                'numero' => $this->genererNumero($request),
                'date_emission' => $data['date_emission'],
                'date_echeance' => $data['date_echeance'] ?? null,
                'notes' => $data['notes'] ?? null,
                'statut' => 'envoyee',
                'created_by' => $request->user()->id,
            ]);

            $total = 0;
            foreach ($data['lignes'] as $ligne) {
                $montant = round($ligne['quantite'] * $ligne['prix_unitaire'], 2);
                $total += $montant;
                $facture->lignes()->create([...$ligne, 'montant' => $montant]);
            }

            $facture->update(['montant_total' => $total]);

            return $facture;
        });

        return response()->json($facture->load('lignes'), 201);
    }

    public function show(Facture $facture)
    {
        return $facture->load(['patient', 'lignes', 'paiements']);
    }

    public function update(Request $request, Facture $facture)
    {
        $data = $request->validate([
            'date_echeance' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'statut' => ['sometimes', 'in:brouillon,envoyee,partiellement_payee,payee,annulee'],
        ]);

        $facture->update($data);

        return response()->json($facture);
    }

    public function destroy(Facture $facture)
    {
        $facture->delete();

        return response()->json(status: 204);
    }

    private function genererNumero(Request $request): string
    {
        $cabinetId = $request->user()->cabinet_id;
        $annee = now()->format('Y');
        $sequence = Facture::where('cabinet_id', $cabinetId)->count() + 1;

        return sprintf('FAC-%s-%05d', $annee, $sequence);
    }
}
