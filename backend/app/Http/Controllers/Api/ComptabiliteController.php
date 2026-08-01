<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComptabiliteController extends Controller
{
    /**
     * Journal de caisse : recettes (paiements) et dépenses mêlées par ordre
     * chronologique, avec solde courant.
     */
    public function journal(Request $request)
    {
        $mouvements = $this->mouvements($request);

        $solde = 0;
        $mouvements = $mouvements->map(function ($mouvement) use (&$solde) {
            $solde += $mouvement['type'] === 'recette' ? $mouvement['montant'] : -$mouvement['montant'];
            $mouvement['solde'] = round($solde, 2);

            return $mouvement;
        });

        return response()->json([
            'total_recettes' => round((float) $mouvements->where('type', 'recette')->sum('montant'), 2),
            'total_depenses' => round((float) $mouvements->where('type', 'depense')->sum('montant'), 2),
            'solde' => round($solde, 2),
            'mouvements' => $mouvements->values(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $mouvements = $this->mouvements($request);

        return response()->streamDownload(function () use ($mouvements) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Type', 'Catégorie', 'Désignation', 'Montant']);

            foreach ($mouvements as $mouvement) {
                fputcsv($handle, [
                    $mouvement['date'],
                    $mouvement['type'] === 'recette' ? 'Recette' : 'Dépense',
                    $mouvement['categorie'],
                    $mouvement['designation'],
                    $mouvement['montant'],
                ]);
            }

            fclose($handle);
        }, 'journal-de-caisse.csv', ['Content-Type' => 'text/csv']);
    }

    private function mouvements(Request $request)
    {
        $debut = $request->query('debut', now()->startOfMonth()->toDateString());
        $fin = $request->query('fin', now()->toDateString());

        $recettes = Paiement::whereBetween('date_paiement', [$debut, $fin])
            ->with('facture.patient')
            ->get()
            ->map(fn (Paiement $p) => [
                'date' => $p->date_paiement->toDateString(),
                'type' => 'recette',
                'categorie' => 'Paiement patient',
                'designation' => $p->facture?->patient?->nom_complet ?? $p->facture?->numero ?? 'Paiement',
                'montant' => (float) $p->montant,
            ]);

        $depenses = Depense::whereBetween('date_depense', [$debut, $fin])
            ->get()
            ->map(fn (Depense $d) => [
                'date' => $d->date_depense->toDateString(),
                'type' => 'depense',
                'categorie' => $d->categorie,
                'designation' => $d->designation,
                'montant' => (float) $d->montant,
            ]);

        return $recettes->concat($depenses)->sortBy('date')->values();
    }
}
