<?php

namespace App\Http\Controllers\Api\Directory;

use App\Enums\StatutDemandeRendezVous;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DemandeRendezVous;
use App\Models\JournalAction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Demande de contact/rendez-vous publique, sans compte ni donnée médicale
 * (mission Partie F). La plateforme n'annonce jamais de rendez-vous
 * "confirmé" de son propre chef : le statut reste "en_attente" jusqu'à ce
 * que le partenaire traite la demande depuis son tableau de bord.
 */
class DemandeRendezVousController extends Controller
{
    public function store(Request $request, Cabinet $cabinet)
    {
        abort_unless($cabinet->estPubliee(), 404);

        $data = $request->validate([
            'praticien_id' => ['nullable', 'integer'],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_souhaitee' => ['required', 'date', 'after_or_equal:today'],
            'creneau_souhaite' => ['nullable', 'string', 'max:100'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['praticien_id'])) {
            $existe = User::where('id', $data['praticien_id'])->where('cabinet_id', $cabinet->id)->exists();
            abort_unless($existe, 422, "Ce praticien n'appartient pas à cette fiche.");
        }

        // Anti-spam simple : pas plus d'une demande identique (même
        // contact, même cabinet, même jour) déjà en attente de traitement.
        // Route publique, donc sans utilisateur authentifié : CabinetScope
        // ne filtre rien ici (comportement normal, pas de global scope à
        // désactiver explicitement).
        $doublon = DemandeRendezVous::where('cabinet_id', $cabinet->id)
            ->where('telephone', $data['telephone'])
            ->where('date_souhaitee', $data['date_souhaitee'])
            ->where('statut', 'en_attente')
            ->exists();

        abort_if($doublon, 422, 'Une demande est déjà en attente pour ce contact et cette date.');

        $demande = DemandeRendezVous::create([
            'cabinet_id' => $cabinet->id,
            'statut' => StatutDemandeRendezVous::EnAttente->value,
            ...$data,
        ]);

        JournalAction::enregistrer('directory.demande_rendez_vous_creee', DemandeRendezVous::class, $demande->id);

        return response()->json($demande, 201);
    }
}
