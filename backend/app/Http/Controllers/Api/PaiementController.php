<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    public function index(Facture $facture)
    {
        return $facture->paiements()->latest('date_paiement')->get();
    }

    /**
     * Enregistre un paiement (total ou échelonné) sur une facture.
     */
    public function store(Request $request, Facture $facture)
    {
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01', 'max:'.$facture->solde],
            'mode_paiement' => ['required', 'in:especes,carte,mobile_money,virement,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_paiement' => ['required', 'date'],
            'echeance_numero' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $paiement = DB::transaction(function () use ($data, $facture, $request) {
            $paiement = $facture->paiements()->create([
                ...$data,
                'created_by' => $request->user()->id,
            ]);

            $facture->increment('montant_paye', $data['montant']);
            $facture->refresh()->rafraichirStatutPaiement();

            return $paiement;
        });

        return response()->json($paiement->load('facture'), 201);
    }

    public function destroy(Facture $facture, Paiement $paiement)
    {
        abort_if($paiement->facture_id !== $facture->id, 404);

        DB::transaction(function () use ($facture, $paiement) {
            $facture->decrement('montant_paye', $paiement->montant);
            $paiement->delete();
            $facture->refresh()->rafraichirStatutPaiement();
        });

        return response()->json(status: 204);
    }
}
