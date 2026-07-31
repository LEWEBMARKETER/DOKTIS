<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\CabinetHoraire;
use App\Models\CabinetService;
use App\Models\Mutuelle;
use App\Models\Specialite;
use Illuminate\Http\Request;

/**
 * Paramètres du cabinet courant (Administrateur), utilisés à la fois par
 * DOKTA Office et exposés en lecture par DOKTA Directory.
 */
class ParametresController extends Controller
{
    public function show(Request $request)
    {
        return Cabinet::with(['specialites', 'mutuelles', 'photos', 'horaires', 'services'])
            ->findOrFail($request->user()->cabinet_id);
    }

    public function update(Request $request)
    {
        $cabinet = Cabinet::findOrFail($request->user()->cabinet_id);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'langues_parlees' => ['nullable', 'array'],
            'accepte_urgences' => ['sometimes', 'boolean'],
            'accessible_pmr' => ['sometimes', 'boolean'],
            'site_web' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'visible_annuaire' => ['sometimes', 'boolean'],
            'specialite_ids' => ['sometimes', 'array'],
            'specialite_ids.*' => ['exists:specialites,id'],
            'mutuelle_ids' => ['sometimes', 'array'],
            'mutuelle_ids.*' => ['exists:mutuelles,id'],
        ]);

        $cabinet->update(collect($data)->except(['specialite_ids', 'mutuelle_ids'])->all());

        if (array_key_exists('specialite_ids', $data)) {
            $cabinet->specialites()->sync($data['specialite_ids']);
        }

        if (array_key_exists('mutuelle_ids', $data)) {
            $cabinet->mutuelles()->sync($data['mutuelle_ids']);
        }

        return response()->json($cabinet->load(['specialites', 'mutuelles']));
    }

    /**
     * Remplace les horaires de la semaine en une fois (7 entrées, une par jour).
     */
    public function updateHoraires(Request $request)
    {
        $data = $request->validate([
            'horaires' => ['required', 'array', 'size:7'],
            'horaires.*.jour_semaine' => ['required', 'integer', 'min:0', 'max:6'],
            'horaires.*.heure_ouverture' => ['nullable', 'date_format:H:i'],
            'horaires.*.heure_fermeture' => ['nullable', 'date_format:H:i'],
            'horaires.*.ferme' => ['sometimes', 'boolean'],
        ]);

        $cabinetId = $request->user()->cabinet_id;

        foreach ($data['horaires'] as $horaire) {
            CabinetHoraire::updateOrCreate(
                ['cabinet_id' => $cabinetId, 'jour_semaine' => $horaire['jour_semaine']],
                [
                    'heure_ouverture' => $horaire['heure_ouverture'] ?? null,
                    'heure_fermeture' => $horaire['heure_fermeture'] ?? null,
                    'ferme' => $horaire['ferme'] ?? false,
                ]
            );
        }

        return response()->json(CabinetHoraire::where('cabinet_id', $cabinetId)->orderBy('jour_semaine')->get());
    }

    public function services(Request $request)
    {
        return CabinetService::where('cabinet_id', $request->user()->cabinet_id)->get();
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_indicatif' => ['nullable', 'numeric', 'min:0'],
            'duree_minutes' => ['nullable', 'integer', 'min:5'],
        ]);

        $service = CabinetService::create([...$data, 'cabinet_id' => $request->user()->cabinet_id]);

        return response()->json($service, 201);
    }

    public function updateService(Request $request, CabinetService $service)
    {
        abort_if($service->cabinet_id !== $request->user()->cabinet_id, 404);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_indicatif' => ['nullable', 'numeric', 'min:0'],
            'duree_minutes' => ['nullable', 'integer', 'min:5'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $service->update($data);

        return response()->json($service);
    }

    public function destroyService(Request $request, CabinetService $service)
    {
        abort_if($service->cabinet_id !== $request->user()->cabinet_id, 404);
        $service->delete();

        return response()->json(status: 204);
    }

    public function mutuellesDisponibles()
    {
        return Mutuelle::orderBy('nom')->get();
    }

    public function specialitesDisponibles()
    {
        return Specialite::orderBy('nom')->get();
    }
}
