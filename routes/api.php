<?php

use App\Http\Controllers\AgencieController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CampainController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollectionCreditController;
use App\Http\Controllers\CollectionPaymentController;
use App\Http\Controllers\CondonationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);

Route::middleware(['check.token'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('credits', CreditController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('managements', ManagementController::class);
    Route::apiResource('payments', CollectionPaymentController::class);
    Route::apiResource('businesses', BusinessController::class);
    // Actualizar orden de prelación
    Route::patch('businesses/{business}/prelation', [BusinessController::class, 'updatePrelation']);
    Route::apiResource('agencies', AgencieController::class);
    Route::apiResource('syncs', SyncController::class);
    Route::apiResource('templates', TemplateController::class);
    
    // --------------------------------------------------------------------------------------------------------------
    Route::apiResource('campains', CampainController::class);
    // Transferencia de créditos
    Route::patch('campains/transfer/{id}', [CampainController::class, 'transfer']);
    // --------------------------------------------------------------------------------------------------------------    
    Route::apiResource('condonations', CondonationController::class);
    // Revertir condonación
    Route::post('condonations/revert/{id}', [CondonationController::class, 'revert']);
    // Autorizar condonación
    Route::post('condonations/authorize/{id}', [CondonationController::class, 'authorizeCondonation']);
    
    // -------------------------------------------------------------------------------------------------------------- 
    Route::apiResource('agreements', AgreementController::class);
    // Autorizar acuerdo
    Route::post('agreements/authorize/{id}', [AgreementController::class, 'authorizeAgreement']);
    // Revertir acuerdo
    Route::post('agreements/revert/{id}', [AgreementController::class, 'revert']);
    // --------------------------------------------------------------------------------------------------------------
    
    // Generar llamada ASTERISK
    Route::post('calls/dial', [CallController::class, 'dial']);
    //  Colgar llamada ASTERISK
    Route::post('calls/hangup', [CallController::class, 'hangup']);
    Route::get('calls', [CallController::class, 'index']);
    Route::post('calls', [CallController::class, 'store']);
    
    // --------------------------------------------------------------------------------------------------------------
    //  Módulos para migraciones masivas
    Route::post('ImportCredits', [ImportController::class, 'importCredits']);
    Route::post('ImportClients', [ImportController::class, 'importClients']);

    // --------------------------------------------------------------------------------------------------------------
    // Desconectar usuario
    Route::post('logout', [LoginController::class, 'logout']);
    // --------------------------------------------------------------------------------------------------------------
    // Monitor de usuarios
    Route::get('monitor', [UserController::class, 'monitor']);
    Route::get('number-trays', [CreditController::class, 'indexNumberTrays']);

    // Estadísticas
    Route::get('statistics/payments-with-management', [StatisticController::class, 'getPaymentsWithManagement']);
    Route::get('statistics/metrics', [StatisticController::class, 'getMetrics']);

    // Obtener llamadas por gestión
    Route::get('managements/{management_id}/calls', [ManagementController::class, 'indexCallsByManagementID']);

    // Rutas para collection credits
    Route::get('collection-credits', [CollectionCreditController::class, 'index']);
    Route::post('collection-credits/save-currently-campain', [CollectionCreditController::class, 'saveCurrentlyCampain']);
});