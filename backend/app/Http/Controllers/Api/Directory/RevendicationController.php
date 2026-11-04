<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DemandeRevendication;
use Illuminate\Http\Request;

/**
 * Un praticien demande à gérer une fiche créée par l'équipe DOKTIS. La
 * demande est vérifiée à la main par le Super Admin (appel au cabinet)
 * avant la création du compte.
 */
class RevendicationController extends Controller
{
    public function store(Request $request, string $identifiant)
    {
        $cabinet = Cabinet::query()
            ->visiblesDansAnnuaire()
            ->where(ctype_digit($identifiant) ? 'id' : 'slug', $identifiant)
            ->firstOrFail();

        abort_if(
            $cabinet->statut_fiche !== Cabinet::STATUT_NON_REVENDIQUEE,
            422,
            'Cette fiche est déjà gérée par son cabinet. Contactez le support DOKTIS si vous pensez que c\'est une erreur.'
        );

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['required', 'string', 'max:50'],
            'fonction' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.unique' => 'Un compte DOKTIS existe déjà avec cet e-mail. Connectez-vous ou utilisez une autre adresse.',
        ]);

        $dejaEnCours = DemandeRevendication::where('cabinet_id', $cabinet->id)
            ->where('email', $data['email'])
            ->where('statut', 'en_attente')
            ->exists();

        abort_if($dejaEnCours, 422, 'Une demande est déjà en cours pour cette fiche avec cet e-mail.');

        DemandeRevendication::create([...$data, 'cabinet_id' => $cabinet->id]);

        return response()->json([
            'message' => "Demande reçue. L'équipe DOKTIS va appeler le cabinet pour vérifier, puis activera votre accès.",
        ], 201);
    }
}
