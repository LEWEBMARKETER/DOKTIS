<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\CabinetController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ComptabiliteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\DevisController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\OrdonnanceController;
use App\Http\Controllers\Api\OrdonnanceModeleController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ParametresController;
use App\Http\Controllers\Api\Patient\AuthController as PatientAuthController;
use App\Http\Controllers\Api\Patient\AvisController as PatientAvisController;
use App\Http\Controllers\Api\Patient\RendezVousController as PatientRendezVousController;
use App\Http\Controllers\Api\Patient\DossierController as PatientDossierController;
use App\Http\Controllers\Api\Patient\MessagerieController as PatientMessagerieController;
use App\Http\Controllers\Api\Directory\CabinetController as DirectoryCabinetController;
use App\Http\Controllers\Api\Directory\SpecialiteController as DirectorySpecialiteController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PlanTraitementController;
use App\Http\Controllers\Api\RendezVousController;
use App\Http\Controllers\Api\SchemaDentaireController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\SuperAdmin\AbonnementController;
use App\Http\Controllers\Api\SuperAdmin\IncidentController;
use App\Http\Controllers\Api\SuperAdmin\JournalController;
use App\Http\Controllers\Api\SuperAdmin\StatistiquesController as SuperAdminStatistiquesController;
use App\Http\Controllers\Api\SuperAdmin\TicketController as SuperAdminTicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DOKTA Office — personnel du cabinet
|--------------------------------------------------------------------------
*/

Route::post('/auth/register-cabinet', [AuthController::class, 'registerCabinet']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/mot-de-passe-oublie', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/auth/reinitialiser-mot-de-passe', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/schema-dentaire', [SchemaDentaireController::class, 'index']);
    Route::get('patients/{patient}/schema-dentaire/historique', [SchemaDentaireController::class, 'historique']);
    Route::post('patients/{patient}/schema-dentaire', [SchemaDentaireController::class, 'store']);
    Route::apiResource('rendez-vous', RendezVousController::class)->parameters(['rendez-vous' => 'rendezVous']);
    Route::post('rendez-vous/{rendezVous}/reprogrammer', [RendezVousController::class, 'reprogrammer']);
    Route::apiResource('consultations', ConsultationController::class);

    Route::apiResource('plans-traitement', PlanTraitementController::class)->parameters(['plans-traitement' => 'planTraitement']);
    Route::patch('plans-traitement/{planTraitement}/etapes/{etape}', [PlanTraitementController::class, 'majEtape']);

    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'destroy']);
    Route::get('documents/{document}/telecharger', [DocumentController::class, 'download']);

    Route::apiResource('ordonnance-modeles', OrdonnanceModeleController::class)->except('show')->parameters(['ordonnance-modeles' => 'ordonnanceModele']);
    Route::apiResource('ordonnances', OrdonnanceController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::get('factures', [FactureController::class, 'index'])->middleware('role:administrateur,secretaire');
    Route::get('factures/{facture}', [FactureController::class, 'show'])->middleware('role:administrateur,secretaire');
    Route::post('factures', [FactureController::class, 'store'])->middleware('role:administrateur,secretaire');
    Route::patch('factures/{facture}', [FactureController::class, 'update'])->middleware('role:administrateur,secretaire');
    Route::delete('factures/{facture}', [FactureController::class, 'destroy'])->middleware('role:administrateur');
    Route::get('factures/{facture}/paiements', [PaiementController::class, 'index'])->middleware('role:administrateur,secretaire');
    Route::post('factures/{facture}/paiements', [PaiementController::class, 'store'])->middleware('role:administrateur,secretaire');
    Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy'])->middleware('role:administrateur');

    Route::apiResource('devis', DevisController::class)->parameters(['devis' => 'devis'])->middleware('role:administrateur,secretaire');
    Route::post('devis/{devis}/convertir', [DevisController::class, 'convertir'])->middleware('role:administrateur,secretaire');

    Route::apiResource('depenses', DepenseController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:administrateur');
    Route::get('comptabilite/journal-caisse', [ComptabiliteController::class, 'journal'])->middleware('role:administrateur');
    Route::get('comptabilite/journal-caisse/export', [ComptabiliteController::class, 'exportCsv'])->middleware('role:administrateur');

    Route::get('users', [UserController::class, 'index']);

    Route::get('avis', [AvisController::class, 'index']);
    Route::patch('avis/{avi}', [AvisController::class, 'repondre']);

    Route::get('conversations', [MessagerieController::class, 'index']);
    Route::get('conversations/{conversation}/messages', [MessagerieController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [MessagerieController::class, 'envoyer']);

    Route::get('support/tickets', [SupportController::class, 'index']);
    Route::post('support/tickets', [SupportController::class, 'store']);
    Route::get('support/tickets/{ticket}', [SupportController::class, 'show']);
    Route::post('support/tickets/{ticket}/messages', [SupportController::class, 'repondre']);

    Route::middleware('role:administrateur')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::get('parametres/cabinet', [ParametresController::class, 'show']);
        Route::patch('parametres/cabinet', [ParametresController::class, 'update']);
        Route::put('parametres/horaires', [ParametresController::class, 'updateHoraires']);
        Route::get('parametres/services', [ParametresController::class, 'services']);
        Route::post('parametres/services', [ParametresController::class, 'storeService']);
        Route::patch('parametres/services/{service}', [ParametresController::class, 'updateService']);
        Route::delete('parametres/services/{service}', [ParametresController::class, 'destroyService']);
        Route::get('parametres/mutuelles-disponibles', [ParametresController::class, 'mutuellesDisponibles']);
        Route::get('parametres/specialites-disponibles', [ParametresController::class, 'specialitesDisponibles']);
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('cabinets', CabinetController::class)->only(['index', 'show', 'update']);

        Route::get('super-admin/statistiques', [SuperAdminStatistiquesController::class, 'index']);

        Route::get('super-admin/abonnements', [AbonnementController::class, 'index']);
        Route::post('super-admin/abonnements', [AbonnementController::class, 'store']);
        Route::patch('super-admin/abonnements/{abonnement}', [AbonnementController::class, 'update']);

        Route::get('super-admin/tickets', [SuperAdminTicketController::class, 'index']);
        Route::get('super-admin/tickets/{ticket}', [SuperAdminTicketController::class, 'show']);
        Route::patch('super-admin/tickets/{ticket}', [SuperAdminTicketController::class, 'update']);
        Route::post('super-admin/tickets/{ticket}/messages', [SuperAdminTicketController::class, 'repondre']);

        Route::apiResource('super-admin/incidents', IncidentController::class)->only(['index', 'store', 'update']);

        Route::get('super-admin/journal', [JournalController::class, 'index']);
    });
});

/*
|--------------------------------------------------------------------------
| DOKTA Patient — application patient (web/PWA)
|--------------------------------------------------------------------------
*/

Route::prefix('patient')->group(function () {
    Route::post('/auth/register', [PatientAuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/login', [PatientAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'patient.account'])->group(function () {
        Route::post('/auth/logout', [PatientAuthController::class, 'logout']);
        Route::get('/auth/me', [PatientAuthController::class, 'me']);
        Route::patch('/profil', [PatientAuthController::class, 'updateProfile']);

        Route::get('/mes-avis', [PatientAvisController::class, 'mesAvis']);
        Route::post('/cabinets/{cabinet}/avis', [PatientAvisController::class, 'store']);
        Route::delete('/cabinets/{cabinet}/avis', [PatientAvisController::class, 'destroy']);

        Route::get('/rendez-vous', [PatientRendezVousController::class, 'index']);
        Route::post('/rendez-vous', [PatientRendezVousController::class, 'store']);
        Route::post('/rendez-vous/{rendezVous}/annuler', [PatientRendezVousController::class, 'annuler']);

        Route::get('/mes-dossiers', [PatientDossierController::class, 'index']);
        Route::get('/mes-ordonnances', [PatientDossierController::class, 'ordonnances']);
        Route::get('/mes-documents', [PatientDossierController::class, 'documents']);
        Route::get('/mes-documents/{document}/telecharger', [PatientDossierController::class, 'telechargerDocument']);
        Route::get('/mes-factures', [PatientDossierController::class, 'factures']);
        Route::get('/mes-plans-traitement', [PatientDossierController::class, 'plansTraitement']);

        Route::get('/conversations', [PatientMessagerieController::class, 'index']);
        Route::post('/cabinets/{cabinet}/conversation', [PatientMessagerieController::class, 'demarrer']);
        Route::get('/conversations/{conversation}/messages', [PatientMessagerieController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [PatientMessagerieController::class, 'envoyer']);
    });
});

/*
|--------------------------------------------------------------------------
| DOKTA Directory — annuaire public (aucune authentification)
|--------------------------------------------------------------------------
*/

Route::prefix('directory')->group(function () {
    Route::get('/cabinets', [DirectoryCabinetController::class, 'index']);
    Route::get('/cabinets/{cabinet}', [DirectoryCabinetController::class, 'show']);
    Route::get('/specialites', [DirectorySpecialiteController::class, 'index']);
});
