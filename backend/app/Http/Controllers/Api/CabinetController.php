<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réservé au super administrateur DOKTA : administration multi-cabinets et
 * création des fiches de l'annuaire (fiches pré-remplies, à revendiquer).
 */
class CabinetController extends Controller
{
    public function index(Request $request)
    {
        return Cabinet::withCount('users', 'patients')
            ->withCount(['demandesRevendication as revendications_en_attente_count' => fn ($q) => $q->where('statut', 'en_attente')])
            ->when($request->query('statut_fiche'), fn ($q, $statut) => $q->where('statut_fiche', $statut))
            ->when($request->query('ville'), fn ($q, $ville) => $q->where('ville', 'ilike', '%'.addcslashes($ville, '%_\\').'%'))
            ->orderBy('nom')
            ->get();
    }

    /**
     * Crée une fiche d'annuaire sans compte associé (« non revendiquée »).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            ...$this->reglesFiche(),
            'nom' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
        ]);

        $cabinet = Cabinet::create([
            ...collect($data)->except(['specialite_ids', 'horaires'])->all(),
            'statut_fiche' => $data['statut_fiche'] ?? Cabinet::STATUT_NON_REVENDIQUEE,
            'plan' => 'annuaire',
        ]);

        if (isset($data['specialite_ids'])) {
            $cabinet->specialites()->sync($data['specialite_ids']);
        }

        if (isset($data['horaires'])) {
            $cabinet->remplacerHoraires($data['horaires']);
        }

        return response()->json($cabinet->load(['specialites', 'horaires']), 201);
    }

    public function show(Cabinet $cabinet)
    {
        return $cabinet->loadCount('users', 'patients')->load(['specialites', 'horaires', 'services']);
    }

    public function update(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate([
            ...$this->reglesFiche(),
            'nom' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', 'string', 'max:50'],
            'actif' => ['sometimes', 'boolean'],
            'essai_expire_at' => ['nullable', 'date'],
        ]);

        $cabinet->update(collect($data)->except(['specialite_ids', 'horaires'])->all());

        if (array_key_exists('specialite_ids', $data)) {
            $cabinet->specialites()->sync($data['specialite_ids']);
        }

        if (isset($data['horaires'])) {
            $cabinet->remplacerHoraires($data['horaires']);
        }

        return response()->json($cabinet->load(['specialites', 'horaires']));
    }

    public function updateHoraires(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate(ReglesHoraires::regles(requis: true));
        $cabinet->remplacerHoraires($data['horaires']);

        return response()->json($cabinet->horaires()->get());
    }

    private function reglesFiche(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['dentaire', 'medical', 'mixte'])],
            'telephone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{8,25}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'langues_parlees' => ['nullable', 'array'],
            'langues_parlees.*' => ['string', 'max:50'],
            'accepte_urgences' => ['sometimes', 'boolean'],
            'accessible_pmr' => ['sometimes', 'boolean'],
            'site_web' => ['nullable', 'url', 'max:255'],
            'visible_annuaire' => ['sometimes', 'boolean'],
            'statut_fiche' => ['sometimes', Rule::in([Cabinet::STATUT_NON_REVENDIQUEE, Cabinet::STATUT_EN_ATTENTE, Cabinet::STATUT_VERIFIEE])],
            'specialite_ids' => ['sometimes', 'array'],
            'specialite_ids.*' => ['exists:specialites,id'],
            ...ReglesHoraires::regles(requis: false),
        ];
    }
}
