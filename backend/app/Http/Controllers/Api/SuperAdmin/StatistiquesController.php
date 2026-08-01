<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\Cabinet;
use App\Models\Facture;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\TicketSupport;

class StatistiquesController extends Controller
{
    public function index()
    {
        $mStart = now()->startOfMonth();

        return response()->json([
            'total_cabinets' => Cabinet::count(),
            'cabinets_actifs' => Cabinet::where('actif', true)->count(),
            'total_comptes_patients' => PatientAccount::count(),
            'total_dossiers_patients' => Patient::withoutGlobalScopes()->count(),
            'abonnements_actifs' => Abonnement::where('statut', 'actif')->count(),
            'revenu_recurrent_mensuel' => (float) Abonnement::where('statut', 'actif')
                ->where('cycle_facturation', 'mensuel')
                ->sum('prix'),
            'chiffre_affaires_plateforme_mois' => (float) Facture::withoutGlobalScopes()
                ->where('date_emission', '>=', $mStart)
                ->where('statut', '!=', 'annulee')
                ->sum('montant_total'),
            'tickets_ouverts' => TicketSupport::whereIn('statut', ['ouvert', 'en_cours'])->count(),
        ]);
    }
}
