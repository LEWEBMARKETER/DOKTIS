<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use Illuminate\Support\Facades\DB;

/**
 * Villes et quartiers couverts par l'annuaire, et liste des pages publiques
 * à exposer aux moteurs de recherche.
 */
class LocaliteController extends Controller
{
    public function index()
    {
        $lignes = Cabinet::query()
            ->visiblesDansAnnuaire()
            ->whereNotNull('ville_slug')
            ->select('ville', 'ville_slug', 'quartier', 'quartier_slug', DB::raw('count(*) as total'))
            ->groupBy('ville', 'ville_slug', 'quartier', 'quartier_slug')
            ->get();

        return $lignes->groupBy('ville_slug')->map(function ($groupe, $villeSlug) {
            return [
                'ville' => $groupe->first()->ville,
                'slug' => $villeSlug,
                'total' => (int) $groupe->sum('total'),
                'quartiers' => $groupe->whereNotNull('quartier_slug')
                    ->map(fn ($l) => ['quartier' => $l->quartier, 'slug' => $l->quartier_slug, 'total' => (int) $l->total])
                    ->sortBy('quartier')->values(),
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Fiches et combinaisons « spécialité × ville (× quartier) » non vides,
     * utilisées pour générer le plan du site.
     */
    public function planDuSite()
    {
        $cabinets = Cabinet::query()
            ->visiblesDansAnnuaire()
            ->select('slug', 'updated_at')
            ->orderBy('id')
            ->get();

        $combinaisons = DB::table('cabinets')
            ->join('cabinet_specialite', 'cabinet_specialite.cabinet_id', '=', 'cabinets.id')
            ->join('specialites', 'specialites.id', '=', 'cabinet_specialite.specialite_id')
            ->where('cabinets.actif', true)
            ->where('cabinets.visible_annuaire', true)
            ->whereNotNull('cabinets.ville_slug')
            ->select(
                'specialites.slug as specialite',
                'cabinets.ville_slug as ville',
                'cabinets.quartier_slug as quartier',
                DB::raw('count(*) as total'),
            )
            ->groupBy('specialites.slug', 'cabinets.ville_slug', 'cabinets.quartier_slug')
            ->get();

        $parVille = $combinaisons->groupBy(fn ($c) => $c->specialite.'|'.$c->ville)
            ->map(fn ($g) => ['specialite' => $g->first()->specialite, 'ville' => $g->first()->ville, 'quartier' => null, 'total' => (int) $g->sum('total')]);

        $parQuartier = $combinaisons->whereNotNull('quartier')
            ->map(fn ($c) => ['specialite' => $c->specialite, 'ville' => $c->ville, 'quartier' => $c->quartier, 'total' => (int) $c->total]);

        return [
            'cabinets' => $cabinets,
            'pages' => $parVille->values()->concat($parQuartier->values())->values(),
        ];
    }
}
