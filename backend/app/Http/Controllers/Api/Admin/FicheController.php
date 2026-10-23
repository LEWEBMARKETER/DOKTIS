<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CategorieFiche;
use App\Enums\StatutPublicationFiche;
use App\Enums\StatutVerificationFiche;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\JournalAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Création et cycle de vie des fiches Directory (structures et
 * professionnels indépendants) — réservé au rôle super_admin (route
 * middleware "role:super_admin"). Règle métier fondamentale de la
 * mission : un partenaire ne crée, valide, publie, dépublie ni n'archive
 * jamais sa propre fiche ; seul un administrateur DOKTIS en a le droit.
 */
class FicheController extends Controller
{
    public function index(Request $request)
    {
        $query = Cabinet::query()->withCount('users')->with(['specialites']);

        if ($categorie = $request->query('categorie_fiche')) {
            $query->where('categorie_fiche', $categorie);
        }

        if ($statutPublication = $request->query('statut_publication')) {
            $query->where('statut_publication', $statutPublication);
        }

        if ($statutVerification = $request->query('statut_verification')) {
            $query->where('statut_verification', $statutVerification);
        }

        if ($recherche = $request->query('q')) {
            $query->where('nom', 'ilike', "%{$recherche}%");
        }

        return $query->orderByDesc('created_at')->paginate($request->integer('par_page', 20));
    }

    public function show(Cabinet $cabinet)
    {
        return $cabinet->load([
            'specialites', 'mutuelles', 'photos', 'horaires', 'services', 'users', 'abonnements',
            'invitationsPartenaire' => fn ($q) => $q->latest(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validerFiche($request, creation: true);

        $cabinet = Cabinet::create([
            ...$data,
            'slug' => Str::slug($data['nom']).'-'.Str::lower(Str::random(6)),
            'pays' => $data['pays'] ?? 'Gabon',
            'statut_verification' => StatutVerificationFiche::NonVerifiee->value,
            'statut_publication' => StatutPublicationFiche::Brouillon->value,
            'actif' => true,
        ]);

        if (! empty($data['specialite_ids'])) {
            $cabinet->specialites()->sync($data['specialite_ids']);
        }

        JournalAction::enregistrer('fiche.creee', Cabinet::class, $cabinet->id, ['categorie_fiche' => $cabinet->categorie_fiche->value]);

        return response()->json($cabinet, 201);
    }

    public function update(Request $request, Cabinet $cabinet)
    {
        $data = $this->validerFiche($request, creation: false);

        $cabinet->update(collect($data)->except(['specialite_ids'])->all());

        if (array_key_exists('specialite_ids', $data)) {
            $cabinet->specialites()->sync($data['specialite_ids']);
        }

        JournalAction::enregistrer('fiche.modifiee', Cabinet::class, $cabinet->id, array_keys($data));

        return response()->json($cabinet->load('specialites'));
    }

    public function verifier(Cabinet $cabinet)
    {
        $cabinet->update(['statut_verification' => StatutVerificationFiche::Verifiee->value]);

        JournalAction::enregistrer('fiche.verifiee', Cabinet::class, $cabinet->id);

        return response()->json($cabinet);
    }

    public function publier(Cabinet $cabinet)
    {
        abort_if(
            $cabinet->statut_verification !== StatutVerificationFiche::Verifiee,
            422,
            'Une fiche doit être vérifiée avant de pouvoir être publiée.'
        );

        $cabinet->update(['statut_publication' => StatutPublicationFiche::Publiee->value]);

        JournalAction::enregistrer('fiche.publiee', Cabinet::class, $cabinet->id);

        return response()->json($cabinet);
    }

    public function suspendre(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);

        $cabinet->update(['statut_publication' => StatutPublicationFiche::Suspendue->value]);

        JournalAction::enregistrer('fiche.suspendue', Cabinet::class, $cabinet->id, $data);

        return response()->json($cabinet);
    }

    public function archiver(Cabinet $cabinet)
    {
        $cabinet->update(['statut_publication' => StatutPublicationFiche::Archivee->value]);

        JournalAction::enregistrer('fiche.archivee', Cabinet::class, $cabinet->id);

        return response()->json($cabinet);
    }

    private function validerFiche(Request $request, bool $creation): array
    {
        $regleRequise = $creation ? 'required' : 'sometimes';

        return $request->validate([
            'nom' => [$regleRequise, 'string', 'max:255'],
            'categorie_fiche' => [$regleRequise, Rule::enum(CategorieFiche::class)],
            'type' => ['sometimes', 'in:dentaire,medical,mixte'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'langues_parlees' => ['nullable', 'array'],
            'accepte_urgences' => ['sometimes', 'boolean'],
            'accessible_pmr' => ['sometimes', 'boolean'],
            'site_web' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'specialite_ids' => ['sometimes', 'array'],
            'specialite_ids.*' => ['exists:specialites,id'],
        ]);
    }
}
