<?php

namespace App\Http\Controllers\Api\Directory;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\CabinetStatistiqueJour;
use App\Models\Creneau;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API publique de DOKTA Directory : aucune authentification requise, lecture
 * seule, uniquement les cabinets actifs et visibles dans l'annuaire.
 */
class CabinetController extends Controller
{
    /** Champs internes jamais exposés publiquement. */
    private const CHAMPS_PRIVES = ['plan', 'essai_expire_at', 'actif', 'visible_annuaire', 'logo_path'];

    public function index(Request $request)
    {
        $query = Cabinet::query()
            ->visiblesDansAnnuaire()
            ->with(['specialites', 'mutuelles', 'photos', 'horaires']);

        if ($ville = $request->query('ville')) {
            $this->filtrerParLieu($query, 'ville', $ville);
        }

        if ($quartier = $request->query('quartier')) {
            $this->filtrerParLieu($query, 'quartier', $quartier);
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

        if ($request->boolean('ouvert')) {
            $query->ouvertsMaintenant();
        }

        if ($request->boolean('whatsapp')) {
            $query->whereNotNull('whatsapp');
        }

        if ($recherche = $request->query('q')) {
            $motif = '%'.addcslashes($recherche, '%_\\').'%';
            $query->where(function ($q) use ($motif) {
                $q->where('nom', 'ilike', $motif)
                    ->orWhere('description', 'ilike', $motif)
                    ->orWhereHas('specialites', fn ($sq) => $sq->where('nom', 'ilike', $motif))
                    ->orWhereHas('users', function ($uq) use ($motif) {
                        $uq->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
                            ->where('name', 'ilike', $motif);
                    });
            });
        }

        $page = $query
            ->orderByRaw("case when statut_fiche = 'verifiee' then 0 else 1 end")
            ->orderByDesc('note_moyenne')
            ->orderBy('nom')
            ->paginate(min($request->integer('par_page', 20), 100));

        $page->getCollection()->transform(fn (Cabinet $cabinet) => $this->presenter($cabinet));

        return $page;
    }

    public function show(string $identifiant)
    {
        $cabinet = $this->trouver($identifiant);

        $cabinet->load([
            'specialites', 'mutuelles', 'photos', 'horaires',
            'services' => fn ($q) => $q->where('actif', true)->orderBy('nom'),
            'avis' => fn ($q) => $q->where('statut', 'visible')->with('auteur:id,nom,prenom')->latest(),
            'users' => fn ($q) => $q->select('id', 'cabinet_id', 'name', 'role', 'specialite')
                ->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
                ->where('actif', true),
        ]);

        return $this->presenter($cabinet);
    }

    /**
     * Compteur anonyme : vue de fiche, clic WhatsApp ou clic téléphone.
     */
    public function evenement(Request $request, string $identifiant)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CabinetStatistiqueJour::EVENEMENTS))],
            'source' => ['nullable', Rule::in(array_keys(CabinetStatistiqueJour::SOURCES))],
            'creneau_id' => ['nullable', 'integer'],
        ]);

        $cabinet = $this->trouver($identifiant);
        CabinetStatistiqueJour::enregistrer($cabinet->id, $data['type'], $data['source'] ?? null);

        // Demande WhatsApp sur un créneau précis : on compte la demande sur ce
        // créneau pour que le cabinet voie lesquels sont sollicités.
        if ($data['type'] === 'clic_whatsapp' && ! empty($data['creneau_id'])) {
            Creneau::withoutGlobalScopes()
                ->where('cabinet_id', $cabinet->id)
                ->where('statut', 'libre')
                ->aVenir()
                ->whereKey($data['creneau_id'])
                ->increment('demandes');
        }

        return response()->noContent();
    }

    /**
     * Créneaux libres des prochains jours, pour la demande de rendez-vous.
     */
    public function creneaux(Request $request, string $identifiant)
    {
        $cabinet = $this->trouver($identifiant);
        $jours = max(1, min($request->integer('jours', 14), 31));

        return Creneau::withoutGlobalScopes()
            ->where('cabinet_id', $cabinet->id)
            ->where('statut', 'libre')
            ->aVenir()
            ->where('debut', '<', now()->addDays($jours))
            ->with('praticien:id,name')
            ->orderBy('debut')
            ->limit(300)
            ->get(['id', 'praticien_id', 'debut', 'fin'])
            ->makeHidden(['praticien_id']);
    }

    private function trouver(string $identifiant): Cabinet
    {
        $cabinet = Cabinet::query()
            ->visiblesDansAnnuaire()
            ->when(
                ctype_digit($identifiant),
                fn (Builder $q) => $q->where('id', (int) $identifiant),
                fn (Builder $q) => $q->where('slug', $identifiant),
            )
            ->first();

        abort_unless($cabinet, 404, 'Cette fiche est introuvable.');

        return $cabinet;
    }

    /**
     * Accepte un slug (« nzeng-ayong ») ou un libellé (« Nzeng-Ayong »).
     */
    private function filtrerParLieu(Builder $query, string $champ, string $valeur): void
    {
        $query->where(function (Builder $q) use ($champ, $valeur) {
            $q->where("{$champ}_slug", Str::slug($valeur))
                ->orWhere($champ, 'ilike', '%'.addcslashes($valeur, '%_\\').'%');
        });
    }

    private function presenter(Cabinet $cabinet): Cabinet
    {
        return $cabinet
            ->makeHidden(self::CHAMPS_PRIVES)
            ->setAttribute('ouvert_maintenant', $cabinet->estOuvert());
    }
}
