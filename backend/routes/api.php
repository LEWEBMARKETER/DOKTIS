<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CabinetController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\OrdonnanceController;
use App\Http\Controllers\Api\OrdonnanceModeleController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PlanTraitementController;
use App\Http\Controllers\Api\RendezVousController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register-cabinet', [AuthController::class, 'registerCabinet']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::apiResource('patients', PatientController::class);
    Route::apiResource('rendez-vous', RendezVousController::class)->parameters(['rendez-vous' => 'rendezVous']);
    Route::apiResource('consultations', ConsultationController::class);

    Route::apiResource('plans-traitement', PlanTraitementController::class)->parameters(['plans-traitement' => 'planTraitement']);
    Route::patch('plans-traitement/{planTraitement}/etapes/{etape}', [PlanTraitementController::class, 'majEtape']);

    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('ordonnance-modeles', OrdonnanceModeleController::class)->except('show')->parameters(['ordonnance-modeles' => 'ordonnanceModele']);
    Route::apiResource('ordonnances', OrdonnanceController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::apiResource('factures', FactureController::class);
    Route::get('factures/{facture}/paiements', [PaiementController::class, 'index']);
    Route::post('factures/{facture}/paiements', [PaiementController::class, 'store']);
    Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy']);

    Route::get('users', [UserController::class, 'index']);

    Route::middleware('role:administrateur')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('cabinets', CabinetController::class)->only(['index', 'show', 'update']);
    });
});
