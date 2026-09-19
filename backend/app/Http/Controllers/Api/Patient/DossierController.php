<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Facture;
use App\Models\Ordonnance;
use App\Models\Patient;
use App\Models\PlanTraitement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Vue en lecture seule, pour un patient authentifié, de ses dossiers médicaux
 * dans chaque cabinet où il a consulté (dossier médical partagé, CDC DOKTA Patient).
 */
class DossierController extends Controller
{
    private function dossierIds(Request $request)
    {
        return Patient::withoutGlobalScopes()
            ->where('patient_account_id', $request->user()->id)
            ->pluck('id');
    }

    public function index(Request $request)
    {
        return Patient::withoutGlobalScopes()
            ->where('patient_account_id', $request->user()->id)
            ->with('cabinet:id,nom,logo_path,telephone,ville')
            ->get();
    }

    public function ordonnances(Request $request)
    {
        return Ordonnance::withoutGlobalScopes()
            ->whereIn('patient_id', $this->dossierIds($request))
            ->with(['praticien:id,name,specialite', 'patient' => $this->patientAvecCabinet()])
            ->orderByDesc('date_emission')
            ->get();
    }

    public function documents(Request $request)
    {
        return Document::withoutGlobalScopes()
            ->whereIn('patient_id', $this->dossierIds($request))
            ->with(['patient' => $this->patientAvecCabinet()])
            ->latest()
            ->get();
    }

    public function telechargerDocument(Request $request, int $document)
    {
        $document = Document::withoutGlobalScopes()
            ->whereIn('patient_id', $this->dossierIds($request))
            ->findOrFail($document);
        abort_unless(Storage::disk($document->disque)->exists($document->chemin), 404);

        return Storage::disk($document->disque)->download($document->chemin, $document->nom_fichier);
    }

    public function factures(Request $request)
    {
        return Facture::withoutGlobalScopes()
            ->whereIn('patient_id', $this->dossierIds($request))
            ->with(['lignes', 'paiements', 'patient' => $this->patientAvecCabinet()])
            ->orderByDesc('date_emission')
            ->get();
    }

    public function plansTraitement(Request $request)
    {
        return PlanTraitement::withoutGlobalScopes()
            ->whereIn('patient_id', $this->dossierIds($request))
            ->with(['etapes', 'praticien:id,name,specialite', 'patient' => $this->patientAvecCabinet()])
            ->latest()
            ->get();
    }

    /**
     * Le modèle Patient applique le scope cabinet ; sans le retirer ici, la
     * relation "patient" chargée depuis un compte DOKTA Patient reviendrait
     * toujours vide, quel que soit le contrôleur qui la déclenche.
     */
    private function patientAvecCabinet(): \Closure
    {
        return fn ($query) => $query->withoutGlobalScopes()->select('id', 'cabinet_id')->with('cabinet:id,nom');
    }
}
