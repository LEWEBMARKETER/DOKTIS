<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Vue d'ensemble des comptes partenaires (un par fiche, au plus). Aucune
 * action de création ici : un compte partenaire naît exclusivement d'une
 * invitation activée (Api\Admin\InvitationController +
 * Api\Partenaire\AuthController::activer).
 */
class PartenaireController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', RoleUtilisateur::Partenaire)->with('cabinet:id,nom,categorie_fiche');

        if ($request->boolean('actif')) {
            $query->where('actif', true);
        }

        return $query->orderBy('name')->paginate($request->integer('par_page', 30));
    }

    public function update(Request $request, User $partenaire)
    {
        abort_unless($partenaire->role === RoleUtilisateur::Partenaire, 404);

        $data = $request->validate(['actif' => ['required', 'boolean']]);

        $partenaire->update($data);

        return response()->json($partenaire);
    }
}
