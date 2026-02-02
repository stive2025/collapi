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
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\DirectionController;
use App\Http\Controllers\FieldTripController;
use App\Http\Controllers\GpsPointController;
use App\Http\Controllers\LegalExpenseController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);

Route::apiResource('managements', ManagementController::class);
Route::get('collection-credits/save-currently-campain', [CollectionCreditController::class, 'saveCurrentlyCampain']);
Route::get('campains/associate-managements', [CampainController::class, 'associateManagements']);

Route::middleware(['check.token'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('credits', CreditController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('directions', DirectionController::class);
    Route::apiResource('gps-points', GpsPointController::class);

    // Rutas específicas de payments (deben ir ANTES del apiResource)
    Route::post('payments/sync', [CollectionPaymentController::class, 'syncPayments']);
    Route::get('payments/summary', [CollectionPaymentController::class, 'getPaymentsResume']);
    Route::post('payments/revert/{id}', [CollectionPaymentController::class, 'revertPayment']);
    Route::post('payments/apply/{id}', [CollectionPaymentController::class, 'applyPayment']);
    Route::post('payments/process-invoice', [CollectionPaymentController::class, 'processInvoice']);
    Route::get('sofiaconfig', [CollectionPaymentController::class, 'getSofiaConfig']);

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
    // Denegar condonación
    Route::post('condonations/deny/{id}', [CondonationController::class, 'denyCondonation']);
    // Comprobar si un crédito ya tuvo condonaciones
    Route::get('condonations/credit/{creditId}/has', [CondonationController::class, 'checkCreditCondonation']);
    
    // -------------------------------------------------------------------------------------------------------------- 
    Route::apiResource('agreements', AgreementController::class);
    // Autorizar acuerdo
    Route::post('agreements/authorize/{id}', [AgreementController::class, 'authorizeAgreement']);
    // Revertir acuerdo
    Route::post('agreements/revert/{id}', [AgreementController::class, 'revert']);
    // Denegar acuerdo
    Route::post('agreements/deny/{id}', [AgreementController::class, 'denyAgreement']);
    // Comprobar si un crédito ya tuvo/contempla convenios
    Route::get('agreements/credit/{creditId}/has', [AgreementController::class, 'checkCreditAgreement']);
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
    Route::post('ImportContacts', [ImportController::class, 'importContacts']);
    Route::post('ImportPayments', [ImportController::class, 'importPayments']);

    // --------------------------------------------------------------------------------------------------------------
    // Desconectar usuario
    Route::post('logout', [LoginController::class, 'logout']);
    Route::post('/password/send-code', [PasswordController::class, 'sendResetCode']);
    Route::post('/password/verify-code', [PasswordController::class, 'verifyCode']);
    Route::post('/password/reset', [PasswordController::class, 'resetPassword']);
    // --------------------------------------------------------------------------------------------------------------
    // Monitor de usuarios
    Route::get('monitor', [UserController::class, 'monitor']);
    Route::get('number-trays', [CreditController::class, 'indexNumberTrays']);

    // Estadísticas
    Route::get('statistics/payments-with-management', [StatisticController::class, 'getPaymentsWithManagement']);
    Route::get('statistics/payments-with-management-details', [StatisticController::class, 'getPaymentsWithManagementDetail']);
    Route::get('statistics/metrics-by-user', [StatisticController::class, 'getStadisticsByUserID']);
    Route::get('statistics/metrics', [StatisticController::class, 'getMetrics']);

    // Obtener llamadas por gestión
    Route::get('managements/{management_id}/calls', [ManagementController::class, 'indexCallsByManagementID']);
    // Sincronización masiva de gestiones
    Route::post('managements/sync', [ManagementController::class, 'syncManagements']);
    // Obtener créditos para envío de mensajes
    Route::post('managements/credits-for-messages', [ManagementController::class, 'getCreditsForMessages']);
    // Carga masiva de gestiones
    Route::post('managements/bulk', [ManagementController::class, 'bulkStore']);

    // Sincronización de gastos de cobranza (invoices)
    Route::post('sync/invoices', [SyncController::class, 'syncInvoices']);
    
    // Sincronización de condonaciones
    Route::post('sync/condonations', [SyncController::class, 'syncCondonations']);
    
    // Sincronización de convenios de pago
    Route::post('sync/agreements', [SyncController::class, 'syncAgreements']);
    Route::post('sync/agreements/update-details', [SyncController::class, 'updateAgreements']);

    // Sincronización de clientes
    Route::post('sync/clients', [SyncController::class, 'syncClients']);

    // Sincronización de gastos judiciales
    Route::post('sync/legal-expenses', [SyncController::class, 'syncLegalExpenses']);

    // Sincronización de collection credits
    Route::post('sync/collection-credits', [SyncController::class, 'syncCollectionCredits']);
    
    // Rutas para collection credits
    Route::get('collection-credits', [CollectionCreditController::class, 'index']);

    // Envío de SMS
    Route::post('sms/send', [SmsController::class, 'sendSms']);
    Route::get('sms/check', [SmsController::class, 'checkSms']);

    // Visitas de campo
    Route::get('field-trips', [FieldTripController::class, 'index']);
    Route::patch('field-trips/{creditId}/approval', [FieldTripController::class, 'toggleApproval']);

    // Gastos judiciales
    Route::get('legal-expenses', [LegalExpenseController::class, 'index']);
    Route::patch('legal-expenses/{creditId}', [LegalExpenseController::class, 'update']);
});

// --------------------------------------------------------------------------------------------------------------
// Exportaciones
Route::get('exports/campain', [ExportController::class, 'exportCampain']);
Route::get('exports/accounting', [ExportController::class, 'exportAccounting']);
Route::get('exports/campain-assignments', [ExportController::class, 'exportCampainAssign']);
Route::get('exports/direcciones', [ExportController::class, 'exportDirecciones']);
Route::get('exports/payments-consolidated', [ExportController::class, 'exportPaymentsConsolidated']);
Route::get('exports/condonations', [ExportController::class, 'exportCondonations']);