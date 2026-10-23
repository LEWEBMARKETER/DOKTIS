<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\InvitationPartenaire;
use App\Models\JournalAction;
use Illuminate\Http\Request;

/**
 * Invitation du responsable d'une fiche à activer son compte partenaire.
 * Aucune inscription publique ne permet de s'attribuer une fiche
 * existante : seul un administrateur DOKTIS déclenche ce parcours, après
 * création de la fiche (mission Partie D).
 */
class InvitationController extends Controller
{
    public function store(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        [$jetonClair, $hash] = InvitationPartenaire::genererJeton();

        $invitation = InvitationPartenaire::create([
            'cabinet_id' => $cabinet->id,
            'email' => $data['email'],
            'token_hash' => $hash,
            'invite_par_id' => $request->user()->id,
            'expire_at' => now()->addDays(config('directory.invitation_expire_jours')),
        ]);

        // Jamais le jeton en clair dans le journal (seul son hash est
        // persisté sur l'invitation elle-même) ni dans aucun log applicatif.
        JournalAction::enregistrer('partenaire.invitation_envoyee', Cabinet::class, $cabinet->id, ['email' => $data['email']]);

        return response()->json([
            'invitation' => $invitation->only(['id', 'cabinet_id', 'email', 'expire_at']),
            // Aucun service d'envoi d'e-mail n'est câblé sur cette instance :
            // l'administrateur transmet lui-même ce lien au partenaire
            // (mission Partie I : pas de notification automatique sans
            // canal réellement configuré).
            'lien_activation' => sprintf(
                '%s/partenaire/activer?invitation=%d&jeton=%s',
                rtrim(config('directory.frontend_url'), '/'),
                $invitation->id,
                $jetonClair,
            ),
        ], 201);
    }
}
