<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ProduitAbonnement;
use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\JournalAction;
use App\Models\PaiementAbonnement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbonnementController extends Controller
{
    public function index(Request $request)
    {
        $query = Abonnement::with('cabinet:id,nom');

        if ($cabinetId = $request->query('cabinet_id')) {
            $query->where('cabinet_id', $cabinetId);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        if ($produit = $request->query('produit')) {
            $query->where('produit', $produit);
        }

        return $query->latest()->paginate($request->integer('par_page', 30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cabinet_id' => ['required', 'exists:cabinets,id'],
            // "sometimes" + valeur par défaut pour ne pas casser un appel
            // existant qui ne connaît pas encore ce champ (abonnement
            // DOKTIS Office historique, implicitement "office").
            'produit' => ['sometimes', Rule::enum(ProduitAbonnement::class)],
            'plan' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'numeric', 'min:0'],
            'cycle_facturation' => ['required', 'in:mensuel,annuel'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $abonnement = Abonnement::create(['produit' => ProduitAbonnement::Office->value, ...$data]);

        JournalAction::enregistrer('abonnement.cree', Abonnement::class, $abonnement->id, $data);

        return response()->json($abonnement, 201);
    }

    public function update(Request $request, Abonnement $abonnement)
    {
        $data = $request->validate([
            'plan' => ['sometimes', 'string', 'max:100'],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'statut' => ['sometimes', 'in:actif,suspendu,annule,expire'],
            'date_fin' => ['nullable', 'date'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $abonnement->update($data);

        JournalAction::enregistrer('abonnement.modifie', Abonnement::class, $abonnement->id, $data);

        return response()->json($abonnement);
    }

    /**
     * Enregistre un paiement constaté manuellement (Mobile Money, virement,
     * espèces) — jamais une intégration de paiement en ligne simulée comme
     * réussie (mission Partie G). Prolonge automatiquement date_fin d'un an
     * ou d'un mois selon le cycle de facturation, et réactive un abonnement
     * expiré/suspendu puisqu'un paiement vient d'être constaté.
     */
    public function enregistrerPaiement(Request $request, Abonnement $abonnement)
    {
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0'],
            'mode_paiement' => ['required', 'in:airtel_money,moov_money,virement,especes'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_paiement' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $paiement = PaiementAbonnement::create([
            ...$data,
            'abonnement_id' => $abonnement->id,
            'enregistre_par_id' => $request->user()->id,
        ]);

        $base = $abonnement->date_fin && $abonnement->date_fin->isFuture() ? $abonnement->date_fin : now();
        $abonnement->update([
            'date_fin' => $abonnement->cycle_facturation === 'annuel' ? $base->copy()->addYear() : $base->copy()->addMonth(),
            'statut' => 'actif',
        ]);

        JournalAction::enregistrer('abonnement.paiement_enregistre', Abonnement::class, $abonnement->id, [
            'montant' => $data['montant'],
            'mode_paiement' => $data['mode_paiement'],
        ]);

        return response()->json($paiement->load('abonnement'), 201);
    }
}
