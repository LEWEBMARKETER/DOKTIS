<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Facture;
use App\Models\Patient;
use App\Models\RendezVous;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $aujourdhui = now()->startOfDay();
        $finJournee = now()->endOfDay();
        $debutMois = now()->startOfMonth();

        return response()->json([
            'total_patients' => Patient::where('actif', true)->count(),
            'rendez_vous_aujourdhui' => RendezVous::whereBetween('debut', [$aujourdhui, $finJournee])
                ->where('statut', '!=', 'annule')
                ->count(),
            'consultations_mois' => Consultation::where('date_consultation', '>=', $debutMois)->count(),
            'chiffre_affaires_mois' => (float) Facture::where('date_emission', '>=', $debutMois)
                ->where('statut', '!=', 'annulee')
                ->sum('montant_total'),
            'montant_encaisse_mois' => (float) Facture::where('date_emission', '>=', $debutMois)
                ->sum('montant_paye'),
            'factures_impayees' => Facture::whereIn('statut', ['envoyee', 'partiellement_payee'])->count(),
            'prochains_rendez_vous' => RendezVous::with(['patient', 'praticien'])
                ->where('debut', '>=', now())
                ->where('statut', '!=', 'annule')
                ->orderBy('debut')
                ->limit(5)
                ->get(),
        ]);
    }
}
