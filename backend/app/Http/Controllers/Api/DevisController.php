<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevisController extends Controller
{
    public function index(Request $request)
    {
        $query = Devis::query()->with('patient');

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
            'date_emission' => ['required', 'date'],
            'date_validite' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $devis = DB::transaction(function () use ($data, $request) {
            $devis = Devis::create([
                'patient_id' => $data['patient_id'],
                'numero' => $this->genererNumero($request),
                'date_emission' => $data['date_emission'],
                'date_validite' => $data['date_validite'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $total = 0;
            foreach ($data['lignes'] as $ligne) {
                $montant = round($ligne['quantite'] * $ligne['prix_unitaire'], 2);
                $total += $montant;
                $devis->lignes()->create([...$ligne, 'montant' => $montant]);
            }

            $devis->update(['montant_total' => $total]);

            return $devis;
        });

        return response()->json($devis->load('lignes'), 201);
    }

    public function show(Devis $devis)
    {
        return $devis->load(['patient', 'lignes', 'facture']);
    }

    public function update(Request $request, Devis $devis)
    {
        $data = $request->validate([
            'statut' => ['sometimes', 'in:propose,accepte,refuse,expire'],
            'date_validite' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $devis->update($data);

        return response()->json($devis);
    }

    public function destroy(Devis $devis)
    {
        abort_if($devis->statut === 'converti', 422, 'Ce devis a déjà été converti en facture.');
        $devis->delete();

        return response()->json(status: 204);
    }

    /**
     * Convertit un devis accepté en facture, en reprenant ses lignes.
     */
    public function convertir(Request $request, Devis $devis)
    {
        abort_if($devis->statut === 'converti', 422, 'Ce devis a déjà été converti en facture.');

        $facture = DB::transaction(function () use ($devis, $request) {
            $facture = Facture::create([
                'patient_id' => $devis->patient_id,
                'numero' => $this->genererNumeroFacture($request),
                'date_emission' => now()->toDateString(),
                'statut' => 'envoyee',
                'montant_total' => $devis->montant_total,
                'created_by' => $request->user()->id,
            ]);

            foreach ($devis->lignes as $ligne) {
                $facture->lignes()->create([
                    'designation' => $ligne->designation,
                    'quantite' => $ligne->quantite,
                    'prix_unitaire' => $ligne->prix_unitaire,
                    'montant' => $ligne->montant,
                ]);
            }

            $devis->update(['statut' => 'converti', 'facture_id' => $facture->id]);

            return $facture;
        });

        return response()->json($facture->load('lignes'), 201);
    }

    private function genererNumero(Request $request): string
    {
        $cabinetId = $request->user()->cabinet_id;
        $annee = now()->format('Y');
        $sequence = Devis::where('cabinet_id', $cabinetId)->count() + 1;

        return sprintf('DEV-%s-%05d', $annee, $sequence);
    }

    private function genererNumeroFacture(Request $request): string
    {
        $cabinetId = $request->user()->cabinet_id;
        $annee = now()->format('Y');
        $sequence = Facture::where('cabinet_id', $cabinetId)->count() + 1;

        return sprintf('FAC-%s-%05d', $annee, $sequence);
    }
}
