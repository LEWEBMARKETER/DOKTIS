<?php

namespace App\Http\Controllers\Api\Partenaire;

use App\Enums\ProduitAbonnement;
use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\PaiementAbonnement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultation (lecture seule) de l'abonnement Directory du partenaire et
 * de ses justificatifs de paiement. La création/modification d'un
 * abonnement reste réservée à l'administrateur DOKTIS
 * (Api\SuperAdmin\AbonnementController) — le partenaire ne peut jamais
 * modifier ses propres tarifs (mission Partie D).
 */
class AbonnementController extends Controller
{
    public function show(Request $request)
    {
        $abonnement = Abonnement::where('cabinet_id', $request->user()->cabinet_id)
            ->whereIn('produit', [ProduitAbonnement::DirectoryStructure->value, ProduitAbonnement::DirectoryProfessionnel->value])
            ->with('paiements')
            ->latest('date_debut')
            ->first();

        abort_if(! $abonnement, 404, 'Aucun abonnement Directory associé à cette fiche.');

        return response()->json($abonnement);
    }

    public function recu(Request $request, PaiementAbonnement $paiement): View
    {
        abort_if($paiement->abonnement->cabinet_id !== $request->user()->cabinet_id, 404);

        return view('recus.paiement-abonnement', ['paiement' => $paiement->load('abonnement.cabinet')]);
    }
}
