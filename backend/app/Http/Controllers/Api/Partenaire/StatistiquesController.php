<?php

namespace App\Http\Controllers\Api\Partenaire;

use App\Http\Controllers\Controller;
use App\Models\DemandeRendezVous;
use App\Models\VisiteFiche;
use Illuminate\Http\Request;

/**
 * Tableau de bord statistique du partenaire (mission Partie E). Un
 * partenaire ne voit jamais que les chiffres de sa propre fiche : les
 * requêtes sont explicitement filtrées par cabinet_id (VisiteFiche n'a pas
 * de scope automatique car ses lignes sont écrites par la route publique,
 * sans utilisateur authentifié) ; DemandeRendezVous l'est déjà via
 * CabinetScope, mais on borne quand même la période dans les deux cas.
 *
 * Définitions (à ne jamais présenter autrement) :
 * - "visites" : chargements de la fiche publique, robots connus exclus.
 * - "visiteurs uniques" : estimation par jour (une empreinte revisitant le
 *   même jour n'est comptée qu'une fois ; une revisite un autre jour
 *   recompte, d'où "estimés" et non un chiffre exact sur toute la période).
 * - "clics téléphone/WhatsApp/itinéraire" : des clics, jamais des preuves
 *   d'appel ou de trajet réellement effectués.
 * - "demande" vs "confirmée" : une demande n'est comptée "confirmée" que
 *   lorsque le partenaire l'a explicitement validée depuis ce tableau de
 *   bord — jamais automatiquement.
 */
class StatistiquesController extends Controller
{
    public function index(Request $request)
    {
        $cabinetId = $request->user()->cabinet_id;
        [$debut, $fin] = $this->resoudrePeriode($request);

        $visites = VisiteFiche::where('cabinet_id', $cabinetId)->whereBetween('date', [$debut, $fin]);

        $totaux = [
            'visites' => (int) $visites->clone()->sum('visites'),
            'visiteurs_uniques_estimes' => (int) $visites->clone()->sum('visiteurs_uniques'),
            'clics_telephone' => (int) $visites->clone()->sum('clics_telephone'),
            'clics_whatsapp' => (int) $visites->clone()->sum('clics_whatsapp'),
            'clics_itineraire' => (int) $visites->clone()->sum('clics_itineraire'),
        ];

        $demandes = DemandeRendezVous::whereBetween('created_at', [$debut, $fin]);
        $parStatut = $demandes->clone()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        $totalDemandes = (int) $parStatut->sum();
        $totaux['demandes_rendez_vous'] = $totalDemandes;
        $totaux['demandes_confirmees'] = (int) ($parStatut['confirmee'] ?? 0);
        $totaux['demandes_refusees_ou_annulees'] = (int) (($parStatut['refusee'] ?? 0) + ($parStatut['annulee'] ?? 0));
        $totaux['taux_conversion_visite_vers_demande'] = $totaux['visites'] > 0
            ? round($totalDemandes / $totaux['visites'], 4)
            : 0.0;

        return response()->json([
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],
            'totaux' => $totaux,
            'repartition_demandes_par_statut' => $parStatut,
            'serie_visites' => VisiteFiche::where('cabinet_id', $cabinetId)
                ->whereBetween('date', [$debut, $fin])
                ->orderBy('date')
                ->get(['date', 'visites', 'visiteurs_uniques']),
            'serie_demandes' => DemandeRendezVous::whereBetween('created_at', [$debut, $fin])
                ->selectRaw('date(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }

    private function resoudrePeriode(Request $request): array
    {
        if ($request->filled('debut') && $request->filled('fin')) {
            return [$request->date('debut')->startOfDay(), $request->date('fin')->endOfDay()];
        }

        $jours = $request->integer('periode', 30);
        $jours = in_array($jours, [7, 30, 90], true) ? $jours : 30;

        return [now()->subDays($jours - 1)->startOfDay(), now()->endOfDay()];
    }
}
