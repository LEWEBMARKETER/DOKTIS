<?php

namespace App\Http\Controllers\Api\Partenaire;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\InvitationPartenaire;
use App\Models\JournalAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Activation d'un compte partenaire à partir d'un lien d'invitation
 * (public, sans authentification préalable — le partenaire n'a justement
 * pas encore de compte). La connexion ultérieure réutilise l'endpoint
 * "/auth/login" existant : un partenaire est un User comme un autre,
 * simplement avec le rôle "partenaire" qui ne lui donne aucun droit
 * Office (cf. middlewares "role"/"feature:office" sur les routes Office).
 */
class AuthController extends Controller
{
    public function activer(Request $request)
    {
        $data = $request->validate([
            'invitation' => ['required', 'integer'],
            'jeton' => ['required', 'string'],
            'password' => ['required', Password::min(8)],
        ]);

        $invitation = InvitationPartenaire::find($data['invitation']);

        abort_if(! $invitation || ! $invitation->estValide($data['jeton']), 422, "Ce lien d'invitation est invalide ou a expiré.");

        $user = User::where('email', $invitation->email)->first();

        abort_if($user && $user->cabinet_id !== $invitation->cabinet_id, 422, 'Cet e-mail est déjà associé à un autre compte DOKTIS.');

        if (! $user) {
            $user = new User(['email' => $invitation->email, 'cabinet_id' => $invitation->cabinet_id]);
        }

        $user->name = $user->name ?: $invitation->cabinet->nom;
        $user->role = RoleUtilisateur::Partenaire;
        $user->password = Hash::make($data['password']);
        $user->actif = true;
        $user->save();

        $invitation->update(['utilise_at' => now()]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        JournalAction::enregistrer('partenaire.compte_active', User::class, $user->id, acteur: $user);

        return response()->json(['user' => $user->load('cabinet')], 201);
    }
}
