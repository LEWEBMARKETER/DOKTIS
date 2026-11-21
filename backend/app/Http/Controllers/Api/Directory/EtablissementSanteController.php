<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Controller;
use App\Models\EtablissementSante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API publique du référentiel des structures sanitaires (pharmacies, hôpitaux,
 * cliniques, cabinets, laboratoires...), y compris celles qui ne sont pas
 * encore clientes de DOKTA Office.
 */
class EtablissementSanteController extends Controller
{
    public function index(Request $request)
    {
        $query = EtablissementSante::query()
            ->where('visible_annuaire', true)
            ->where('statut_verification', '!=', 'ferme');

        if ($categorie = $request->query('categorie')) {
            $query->whereIn('categorie', explode(',', $categorie));
        }

        foreach (['ville', 'province', 'quartier'] as $champ) {
            if ($valeur = $request->query($champ)) {
                $query->where($champ, 'ilike', "%{$valeur}%");
            }
        }

        if ($request->boolean('ouvert_24h')) {
            $query->where('ouvert_24h', true);
        }

        if ($recherche = $request->query('q')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('nom', 'ilike', "%{$recherche}%")
                    ->orWhere('praticien', 'ilike', "%{$recherche}%")
                    ->orWhere('quartier', 'ilike', "%{$recherche}%")
                    ->orWhereRaw('specialites::text ilike ?', ["%{$recherche}%"]);
            });
        }

        return $query->orderBy('ville')->orderBy('nom')->paginate($request->integer('par_page', 20));
    }

    public function show(EtablissementSante $etablissement)
    {
        abort_unless($etablissement->visible_annuaire, 404);

        return $etablissement->load('cabinet:id,nom,slug');
    }

    /** Nombre de structures par catégorie (pour les filtres de l'interface). */
    public function categories()
    {
        $totaux = EtablissementSante::query()
            ->where('visible_annuaire', true)
            ->where('statut_verification', '!=', 'ferme')
            ->select('categorie', DB::raw('count(*) as total'))
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        return collect(EtablissementSante::CATEGORIES)
            ->map(fn ($libelle, $code) => ['code' => $code, 'libelle' => $libelle, 'total' => (int) ($totaux[$code] ?? 0)])
            ->values();
    }
}
