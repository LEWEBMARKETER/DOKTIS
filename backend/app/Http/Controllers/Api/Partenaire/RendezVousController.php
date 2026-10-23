<?php

namespace App\Http\Controllers\Api\Partenaire;

use App\Enums\StatutDemandeRendezVous;
use App\Http\Controllers\Controller;
use App\Models\DemandeRendezVous;
use App\Models\JournalAction;
use Illuminate\Http\Request;

/**
 * Traitement par le partenaire des demandes de rendez-vous reçues depuis
 * l'annuaire public (mission Partie D/F). Le global scope CabinetScope
 * (via BelongsToCabinet sur DemandeRendezVous) garantit qu'un partenaire
 * ne voit et ne traite jamais que les demandes de sa propre fiche.
 */
class RendezVousController extends Controller
{
    public function index(Request $request)
    {
        $query = DemandeRendezVous::query();

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->orderByDesc('date_souhaitee')->paginate($request->integer('par_page', 20));
    }

    public function confirmer(DemandeRendezVous $demande)
    {
        $this->abortSiDejaTraitee($demande);

        $demande->update(['statut' => StatutDemandeRendezVous::Confirmee->value, 'traite_at' => now()]);

        JournalAction::enregistrer('partenaire.demande_rdv_confirmee', DemandeRendezVous::class, $demande->id);

        return response()->json($demande);
    }

    public function refuser(Request $request, DemandeRendezVous $demande)
    {
        $this->abortSiDejaTraitee($demande);

        $data = $request->validate(['notes_partenaire' => ['nullable', 'string', 'max:500']]);

        $demande->update([
            'statut' => StatutDemandeRendezVous::Refusee->value,
            'notes_partenaire' => $data['notes_partenaire'] ?? null,
            'traite_at' => now(),
        ]);

        JournalAction::enregistrer('partenaire.demande_rdv_refusee', DemandeRendezVous::class, $demande->id);

        return response()->json($demande);
    }

    public function proposerCreneau(Request $request, DemandeRendezVous $demande)
    {
        $this->abortSiDejaTraitee($demande);

        $data = $request->validate([
            'date_proposee' => ['required', 'date', 'after_or_equal:today'],
            'creneau_propose' => ['nullable', 'string', 'max:100'],
            'notes_partenaire' => ['nullable', 'string', 'max:500'],
        ]);

        // Reste "en_attente" : c'est une proposition à faire valider par
        // le visiteur (par téléphone/WhatsApp — aucun portail invité au
        // lancement), jamais une confirmation annoncée par la plateforme.
        $demande->update($data);

        JournalAction::enregistrer('partenaire.demande_rdv_creneau_propose', DemandeRendezVous::class, $demande->id);

        return response()->json($demande);
    }

    public function terminer(DemandeRendezVous $demande)
    {
        abort_unless($demande->statut === StatutDemandeRendezVous::Confirmee, 422, 'Seule une demande confirmée peut être marquée terminée.');

        $demande->update(['statut' => StatutDemandeRendezVous::Terminee->value]);

        JournalAction::enregistrer('partenaire.demande_rdv_terminee', DemandeRendezVous::class, $demande->id);

        return response()->json($demande);
    }

    private function abortSiDejaTraitee(DemandeRendezVous $demande): void
    {
        abort_unless(
            in_array($demande->statut, [StatutDemandeRendezVous::EnAttente], true),
            422,
            'Cette demande a déjà été traitée.'
        );
    }
}
