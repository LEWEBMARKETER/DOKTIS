<?php

namespace App\Http\Controllers\Api\Directory;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use Illuminate\Http\Request;

/**
 * API publique de DOKTA Directory : aucune authentification requise, lecture
 * seule, uniquement les cabinets actifs et visibles dans l'annuaire.
 */
class CabinetController extends Controller
{
    public function index(Request $request)
    {
        $query = Cabinet::query()
            ->where('actif', true)
            ->where('visible_annuaire', true)
            ->with(['specialites', 'mutuelles', 'photos']);

        if ($ville = $request->query('ville')) {
            $query->where('ville', 'ilike', "%{$ville}%");
        }

        if ($quartier = $request->query('quartier')) {
            $query->where('quartier', 'ilike', "%{$quartier}%");
        }

        if ($specialite = $request->query('specialite')) {
            $query->whereHas('specialites', function ($q) use ($specialite) {
                is_numeric($specialite)
                    ? $q->where('specialites.id', $specialite)
                    : $q->where('slug', $specialite);
            });
        }

        if ($mutuelle = $request->query('mutuelle')) {
            $query->whereHas('mutuelles', fn ($q) => $q->where('mutuelles.id', $mutuelle));
        }

        if ($langue = $request->query('langue')) {
            $query->whereJsonContains('langues_parlees', $langue);
        }

        if ($request->boolean('urgence')) {
            $query->where('accepte_urgences', true);
        }

        if ($request->boolean('pmr')) {
            $query->where('accessible_pmr', true);
        }

        if ($recherche = $request->query('q')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('nom', 'ilike', "%{$recherche}%")
                    ->orWhereHas('users', function ($uq) use ($recherche) {
                        $uq->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
                            ->where('name', 'ilike', "%{$recherche}%");
                    });
            });
        }

        return $query->orderByDesc('note_moyenne')->paginate($request->integer('par_page', 20));
    }

    public function show(Cabinet $cabinet)
    {
        abort_unless($cabinet->actif && $cabinet->visible_annuaire, 404);

        return $cabinet->load([
            'specialites', 'mutuelles', 'photos', 'horaires', 'services',
            'avis' => fn ($q) => $q->where('statut', 'visible')->with('auteur:id,nom,prenom')->latest(),
            'users' => fn ($q) => $q->select('id', 'cabinet_id', 'name', 'role', 'specialite')
                ->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
                ->where('actif', true),
        ]);
    }
}
