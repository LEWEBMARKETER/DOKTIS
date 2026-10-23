<?php

use App\Http\Controllers\Api\Admin\DemandeRendezVousController as AdminDemandeRendezVousController;
use App\Http\Controllers\Api\Admin\FicheController as AdminFicheController;
use App\Http\Controllers\Api\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Api\Admin\ModificationFicheController as AdminModificationFicheController;
use App\Http\Controllers\Api\Admin\PartenaireController as AdminPartenaireController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\CabinetController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ComptabiliteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\DevisController;
use App\Http\Controllers\Api\Directory\DemandeRendezVousController as DirectoryDemandeRendezVousController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\OrdonnanceController;
use App\Http\Controllers\Api\OrdonnanceModeleController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ParametresController;
use App\Http\Controllers\Api\Partenaire\AbonnementController as PartenaireAbonnementController;
use App\Http\Controllers\Api\Partenaire\AuthController as PartenaireAuthController;
use App\Http\Controllers\Api\Partenaire\FicheController as PartenaireFicheController;
use App\Http\Controllers\Api\Partenaire\RendezVousController as PartenaireRendezVousController;
use App\Http\Controllers\Api\Partenaire\StatistiquesController as PartenaireStatistiquesController;
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
| DOKTIS Office — personnel du cabinet
|--------------------------------------------------------------------------
| Repositionnement Directory-first : la création libre d'un cabinet reste
| dans le code (aucune fonctionnalité supprimée) mais répond 403 tant que
| DOKTIS_OFFICE_ENABLED est désactivé — seul un administrateur DOKTIS crée
| une fiche désormais (cf. Api\Admin\FicheController).
*/

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register-cabinet', [AuthController::class, 'registerCabinet'])->middleware('feature:office');
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Logout/me sont une infrastructure d'authentification partagée par tout
// compte "staff" (Office, super_admin ET partenaire Directory) : jamais
// gated par un flag de module produit.
Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

// "role:...,super_admin" exclut explicitement le rôle "partenaire" de tout
// le module Office : un partenaire Directory n'a jamais accès aux dossiers
// médicaux ni à aucune fonctionnalité du cabinet (mission Partie D), même
// scopée à son propre cabinet_id (qui n'en contient normalement aucun).
Route::middleware(['auth:sanctum', 'staff', 'feature:office', 'role:administrateur,medecin,secretaire,assistant,super_admin'])->group(function () {
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
    Route::get('documents/{document}/telecharger', [DocumentController::class, 'telecharger']);

    Route::apiResource('ordonnance-modeles', OrdonnanceModeleController::class)->except('show')->parameters(['ordonnance-modeles' => 'ordonnanceModele']);
    Route::apiResource('ordonnances', OrdonnanceController::class)->only(['index', 'store', 'show', 'destroy']);

    // La suppression d'une facture, d'un paiement, d'un devis ou d'une
    // dépense est réservée aux administrateurs (groupe role:administrateur
    // plus bas) : ce sont des écritures comptables, pas de simples fiches.
    Route::apiResource('factures', FactureController::class)->except('destroy');
    Route::get('factures/{facture}/paiements', [PaiementController::class, 'index']);
    Route::post('factures/{facture}/paiements', [PaiementController::class, 'store']);

    Route::apiResource('devis', DevisController::class)->parameters(['devis' => 'devis'])->except('destroy');
    Route::post('devis/{devis}/convertir', [DevisController::class, 'convertir']);

    Route::apiResource('depenses', DepenseController::class)->only(['index', 'store', 'update']);
    Route::get('comptabilite/journal-caisse', [ComptabiliteController::class, 'journal']);
    Route::get('comptabilite/journal-caisse/export', [ComptabiliteController::class, 'exportCsv']);

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

        Route::delete('factures/{facture}', [FactureController::class, 'destroy']);
        Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy']);
        Route::delete('devis/{devis}', [DevisController::class, 'destroy']);
        Route::delete('depenses/{depense}', [DepenseController::class, 'destroy']);

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
});

/*
|--------------------------------------------------------------------------
| Administration DOKTIS (super_admin) — jamais désactivée par un flag de
| module produit, puisque c'est elle qui pilote ces flags et le cycle de
| vie des fiches Directory.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'staff', 'role:super_admin'])->group(function () {
    Route::apiResource('cabinets', CabinetController::class)->only(['index', 'show', 'update']);

    Route::get('super-admin/statistiques', [SuperAdminStatistiquesController::class, 'index']);

    Route::get('super-admin/abonnements', [AbonnementController::class, 'index']);
    Route::post('super-admin/abonnements', [AbonnementController::class, 'store']);
    Route::patch('super-admin/abonnements/{abonnement}', [AbonnementController::class, 'update']);
    Route::post('super-admin/abonnements/{abonnement}/paiements', [AbonnementController::class, 'enregistrerPaiement']);

    Route::get('super-admin/tickets', [SuperAdminTicketController::class, 'index']);
    Route::get('super-admin/tickets/{ticket}', [SuperAdminTicketController::class, 'show']);
    Route::patch('super-admin/tickets/{ticket}', [SuperAdminTicketController::class, 'update']);
    Route::post('super-admin/tickets/{ticket}/messages', [SuperAdminTicketController::class, 'repondre']);

    Route::apiResource('super-admin/incidents', IncidentController::class)->only(['index', 'store', 'update']);

    Route::get('super-admin/journal', [JournalController::class, 'index']);

    // Cycle de vie des fiches Directory (structures et professionnels
    // indépendants) — création, vérification, publication exclusivement
    // par un administrateur DOKTIS (mission Partie C).
    Route::apiResource('admin/fiches', AdminFicheController::class)->parameters(['fiches' => 'cabinet'])->only(['index', 'show', 'store', 'update']);
    Route::post('admin/fiches/{cabinet}/verifier', [AdminFicheController::class, 'verifier']);
    Route::post('admin/fiches/{cabinet}/publier', [AdminFicheController::class, 'publier']);
    Route::post('admin/fiches/{cabinet}/suspendre', [AdminFicheController::class, 'suspendre']);
    Route::post('admin/fiches/{cabinet}/archiver', [AdminFicheController::class, 'archiver']);

    Route::post('admin/fiches/{cabinet}/invitations', [AdminInvitationController::class, 'store']);

    Route::get('admin/demandes-modification-fiche', [AdminModificationFicheController::class, 'index']);
    Route::post('admin/demandes-modification-fiche/{demande}/valider', [AdminModificationFicheController::class, 'valider']);
    Route::post('admin/demandes-modification-fiche/{demande}/refuser', [AdminModificationFicheController::class, 'refuser']);

    // Vue d'ensemble plateforme (lecture seule) : le traitement reste du
    // ressort du partenaire concerné.
    Route::get('admin/demandes-rendez-vous', [AdminDemandeRendezVousController::class, 'index']);

    Route::get('admin/partenaires', [AdminPartenaireController::class, 'index']);
    Route::patch('admin/partenaires/{partenaire}', [AdminPartenaireController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Espace partenaire DOKTIS Directory
|--------------------------------------------------------------------------
*/

Route::prefix('partenaire')->group(function () {
    // Public : le partenaire n'a pas encore de compte au moment d'activer
    // son invitation (mission Partie D — jamais d'auto-attribution libre
    // d'une fiche existante).
    Route::middleware('throttle:auth')->post('/auth/activer', [PartenaireAuthController::class, 'activer']);

    Route::middleware(['auth:sanctum', 'staff', 'role:partenaire'])->group(function () {
        Route::get('/ma-fiche', [PartenaireFicheController::class, 'show']);
        Route::post('/ma-fiche/demande-modification', [PartenaireFicheController::class, 'demanderModification']);
        Route::get('/mes-demandes-modification', [PartenaireFicheController::class, 'mesDemandesModification']);

        Route::get('/statistiques', [PartenaireStatistiquesController::class, 'index']);

        Route::get('/abonnement', [PartenaireAbonnementController::class, 'show']);
        Route::get('/abonnement/paiements/{paiement}/recu', [PartenaireAbonnementController::class, 'recu']);

        Route::get('/demandes-rendez-vous', [PartenaireRendezVousController::class, 'index']);
        Route::post('/demandes-rendez-vous/{demande}/confirmer', [PartenaireRendezVousController::class, 'confirmer']);
        Route::post('/demandes-rendez-vous/{demande}/refuser', [PartenaireRendezVousController::class, 'refuser']);
        Route::post('/demandes-rendez-vous/{demande}/proposer-creneau', [PartenaireRendezVousController::class, 'proposerCreneau']);
        Route::post('/demandes-rendez-vous/{demande}/terminer', [PartenaireRendezVousController::class, 'terminer']);
    });
});

/*
|--------------------------------------------------------------------------
| DOKTA Patient — application patient (web/PWA)
|--------------------------------------------------------------------------
| Le dossier médical du patient (ordonnances, documents, factures, plans
| de traitement) reste gardé par DOKTIS_PATIENT_MEDICAL_ENABLED : la prise
| de contact public passe désormais par l'annuaire (demandes de
| rendez-vous sans compte, voir plus bas), pas par ce module.
*/

Route::prefix('patient')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [PatientAuthController::class, 'register']);
        Route::post('/auth/login', [PatientAuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'patient.account'])->group(function () {
        Route::post('/auth/logout', [PatientAuthController::class, 'logout']);
        Route::get('/auth/me', [PatientAuthController::class, 'me']);
        Route::patch('/profil', [PatientAuthController::class, 'updateProfile']);

        Route::get('/mes-avis', [PatientAvisController::class, 'mesAvis']);
        Route::post('/cabinets/{cabinet}/avis', [PatientAvisController::class, 'store']);
        Route::delete('/cabinets/{cabinet}/avis', [PatientAvisController::class, 'destroy']);

        Route::middleware('feature:patient_medical')->group(function () {
            Route::get('/rendez-vous', [PatientRendezVousController::class, 'index']);
            Route::post('/rendez-vous', [PatientRendezVousController::class, 'store']);
            Route::post('/rendez-vous/{rendezVous}/annuler', [PatientRendezVousController::class, 'annuler']);

            Route::get('/mes-dossiers', [PatientDossierController::class, 'index']);
            Route::get('/mes-ordonnances', [PatientDossierController::class, 'ordonnances']);
            Route::get('/mes-documents', [PatientDossierController::class, 'documents']);
            Route::get('/documents/{document}/telecharger', [PatientDossierController::class, 'telechargerDocument']);
            Route::get('/mes-factures', [PatientDossierController::class, 'factures']);
            Route::get('/mes-plans-traitement', [PatientDossierController::class, 'plansTraitement']);
        });

        Route::get('/conversations', [PatientMessagerieController::class, 'index']);
        Route::post('/cabinets/{cabinet}/conversation', [PatientMessagerieController::class, 'demarrer']);
        Route::get('/conversations/{conversation}/messages', [PatientMessagerieController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [PatientMessagerieController::class, 'envoyer']);
    });
});

/*
|--------------------------------------------------------------------------
| DOKTIS Directory — annuaire public (aucune authentification)
|--------------------------------------------------------------------------
*/

Route::prefix('directory')->group(function () {
    Route::get('/cabinets', [DirectoryCabinetController::class, 'index']);
    Route::get('/cabinets/{cabinet}', [DirectoryCabinetController::class, 'show']);
    Route::get('/fiches/{cabinet:slug}', [DirectoryCabinetController::class, 'show']);
    Route::get('/specialites', [DirectorySpecialiteController::class, 'index']);

    // Demande de contact public (sans compte, sans donnée médicale) et
    // compteurs de visibilité, limités en fréquence pour éviter tout abus
    // (mission Partie F/I — "limitation des requêtes").
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/cabinets/{cabinet}/demandes-rendez-vous', [DirectoryDemandeRendezVousController::class, 'store']);
        Route::post('/cabinets/{cabinet}/visite', [DirectoryCabinetController::class, 'enregistrerVisite']);
        Route::post('/cabinets/{cabinet}/clic/{type}', [DirectoryCabinetController::class, 'enregistrerClic'])
            ->where('type', 'telephone|whatsapp|itineraire');
    });
});
