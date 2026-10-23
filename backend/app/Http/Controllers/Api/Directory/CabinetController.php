<?php

namespace App\Http\Controllers\Api\Directory;

use App\Enums\CategorieFiche;
use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Services\CollecteStatistiquesFiche;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API publique de DOKTIS Directory : aucune authentification requise,
 * lecture seule, uniquement les fiches actives et publiées (cf.
 * Cabinet::scopePubliees — statut_publication = "publiee").
 */
class CabinetController extends Controller
{
    public function index(Request $request)
    {
        $query = Cabinet::query()
            ->publiees()
            ->with(['specialites', 'mutuelles', 'photos']);

        if ($categorie = $request->query('categorie')) {
            $query->where('categorie_fiche', $categorie === 'professionnel' ? CategorieFiche::ProfessionnelIndependant->value : $categorie);
        }

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
                        $uq->whereIn('role', $this->rolesPraticiensPublics())
                            ->where('name', 'ilike', "%{$recherche}%");
                    });
            });
        }

        if ($request->query('tri') === 'recent') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('note_moyenne');
        }

        return $query->paginate($request->integer('par_page', 20));
    }

    public function show(Cabinet $cabinet)
    {
        abort_unless($cabinet->estPubliee(), 404);

        return $cabinet->load([
            'specialites', 'mutuelles', 'photos', 'horaires', 'services',
            'avis' => fn ($q) => $q->where('statut', 'visible')->with('auteur:id,nom,prenom')->latest(),
            'users' => fn ($q) => $q->select('id', 'cabinet_id', 'name', 'role', 'specialite')
                ->whereIn('role', $this->rolesPraticiensPublics())
                ->where('actif', true),
        ]);
    }

    public function enregistrerVisite(Request $request, Cabinet $cabinet, CollecteStatistiquesFiche $collecte): Response
    {
        abort_unless($cabinet->estPubliee(), 404);

        $collecte->enregistrerVisite($cabinet, $request);

        return response()->noContent();
    }

    public function enregistrerClic(Request $request, Cabinet $cabinet, string $type, CollecteStatistiquesFiche $collecte): Response
    {
        abort_unless($cabinet->estPubliee(), 404);

        $collecte->enregistrerClic($cabinet, $type);

        return response()->noContent();
    }

    /**
     * Rôles dont le nom est affichable comme praticien sur une fiche
     * publique : le personnel Office classique, et le partenaire lui-même
     * pour une fiche "professionnel indépendant" (c'est alors la seule
     * personne rattachée à la fiche).
     */
    private function rolesPraticiensPublics(): array
    {
        return [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value, RoleUtilisateur::Partenaire->value];
    }
}
