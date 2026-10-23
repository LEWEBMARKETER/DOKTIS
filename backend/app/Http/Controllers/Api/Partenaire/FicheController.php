<?php

namespace App\Http\Controllers\Api\Partenaire;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DemandeModificationFiche;
use App\Models\JournalAction;
use Illuminate\Http\Request;

/**
 * Un partenaire consulte sa propre fiche mais ne la modifie jamais
 * directement : il propose des changements (demanderModification), qu'un
 * administrateur DOKTIS valide ou refuse (Api\Admin\ModificationFicheController).
 */
class FicheController extends Controller
{
    public function show(Request $request)
    {
        return Cabinet::with(['specialites', 'mutuelles', 'photos', 'horaires', 'services', 'abonnements'])
            ->findOrFail($request->user()->cabinet_id);
    }

    public function demanderModification(Request $request)
    {
        $data = $request->validate([
            'champs' => ['required', 'array', 'min:1'],
            'champs.description' => ['sometimes', 'nullable', 'string'],
            'champs.telephone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'champs.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'champs.adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'champs.ville' => ['sometimes', 'nullable', 'string', 'max:255'],
            'champs.quartier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'champs.latitude' => ['sometimes', 'nullable', 'numeric'],
            'champs.longitude' => ['sometimes', 'nullable', 'numeric'],
            'champs.langues_parlees' => ['sometimes', 'nullable', 'array'],
            'champs.accepte_urgences' => ['sometimes', 'boolean'],
            'champs.accessible_pmr' => ['sometimes', 'boolean'],
            'champs.site_web' => ['sometimes', 'nullable', 'url', 'max:255'],
            'champs.facebook_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'champs.instagram_url' => ['sometimes', 'nullable', 'url', 'max:255'],
        ]);

        // Seuls les champs listés ci-dessus passent la validation : toute
        // autre clé envoyée est silencieusement ignorée par `validate()`
        // (elle ne peut donc jamais atteindre Cabinet::update côté admin).
        $demande = DemandeModificationFiche::create([
            'cabinet_id' => $request->user()->cabinet_id,
            'partenaire_id' => $request->user()->id,
            'champs_proposes' => $data['champs'],
        ]);

        JournalAction::enregistrer('fiche.modification_demandee', DemandeModificationFiche::class, $demande->id);

        return response()->json($demande, 201);
    }

    public function mesDemandesModification()
    {
        // Portée automatiquement au cabinet du partenaire connecté par le
        // global scope de BelongsToCabinet (CabinetScope).
        return DemandeModificationFiche::latest()->get();
    }
}
