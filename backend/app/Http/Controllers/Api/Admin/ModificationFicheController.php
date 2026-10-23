<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StatutDemandeModificationFiche;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DemandeModificationFiche;
use App\Models\JournalAction;
use Illuminate\Http\Request;

class ModificationFicheController extends Controller
{
    public function index(Request $request)
    {
        // Le rôle super_admin (seul autorisé sur ce contrôleur, cf. routes)
        // n'est jamais filtré par le global scope CabinetScope.
        $query = DemandeModificationFiche::with(['cabinet:id,nom', 'partenaire:id,name']);

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest()->paginate($request->integer('par_page', 20));
    }

    public function valider(Request $request, DemandeModificationFiche $demande)
    {
        abort_if($demande->statut !== StatutDemandeModificationFiche::EnAttente, 422, 'Cette demande a déjà été traitée.');

        $cabinet = Cabinet::findOrFail($demande->cabinet_id);
        $champsAutorises = array_intersect_key($demande->champs_proposes, array_flip($this->champsModifiables()));
        $cabinet->update($champsAutorises);

        $demande->update([
            'statut' => StatutDemandeModificationFiche::Validee->value,
            'traite_par_id' => $request->user()->id,
            'traite_at' => now(),
        ]);

        JournalAction::enregistrer('fiche.modification_validee', Cabinet::class, $cabinet->id, ['demande_id' => $demande->id]);

        return response()->json($demande->fresh());
    }

    public function refuser(Request $request, DemandeModificationFiche $demande)
    {
        abort_if($demande->statut !== StatutDemandeModificationFiche::EnAttente, 422, 'Cette demande a déjà été traitée.');

        $data = $request->validate(['commentaire_admin' => ['nullable', 'string', 'max:500']]);

        $demande->update([
            'statut' => StatutDemandeModificationFiche::Refusee->value,
            'commentaire_admin' => $data['commentaire_admin'] ?? null,
            'traite_par_id' => $request->user()->id,
            'traite_at' => now(),
        ]);

        JournalAction::enregistrer('fiche.modification_refusee', DemandeModificationFiche::class, $demande->id);

        return response()->json($demande);
    }

    /** Liste blanche : un partenaire ne peut jamais faire valider un changement de statut via ce canal. */
    private function champsModifiables(): array
    {
        return [
            'description', 'telephone', 'email', 'adresse', 'ville', 'quartier',
            'latitude', 'longitude', 'langues_parlees', 'accepte_urgences',
            'accessible_pmr', 'site_web', 'facebook_url', 'instagram_url',
        ];
    }
}
