<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CabinetStatistiqueJour;
use Illuminate\Http\Request;

/**
 * Vues et clics de la fiche annuaire du cabinet de l'utilisateur connecté.
 */
class FicheStatistiquesController extends Controller
{
    public function show(Request $request)
    {
        $cabinetId = $request->user()->cabinet_id;
        abort_unless($cabinetId, 404);

        $jours = max(1, min($request->integer('jours', 30), 365));
        $debut = now(config('dokta.fuseau_horaire_local'))->subDays($jours - 1)->toDateString();

        $lignes = CabinetStatistiqueJour::where('cabinet_id', $cabinetId)
            ->where('jour', '>=', $debut)
            ->orderBy('jour')
            ->get(['jour', 'vues', 'clics_whatsapp', 'clics_telephone']);

        return response()->json([
            'periode_jours' => $jours,
            'totaux' => [
                'vues' => (int) $lignes->sum('vues'),
                'clics_whatsapp' => (int) $lignes->sum('clics_whatsapp'),
                'clics_telephone' => (int) $lignes->sum('clics_telephone'),
            ],
            'par_jour' => $lignes,
        ]);
    }
}
