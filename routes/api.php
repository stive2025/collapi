<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CampainController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollectionPaymentController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);

Route::middleware(['check.token'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('credits', CreditController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('managements', ManagementController::class);
    Route::apiResource('payments', CollectionPaymentController::class);
    Route::apiResource('businesses', BusinessController::class);
    Route::apiResource('campains', CampainController::class);

    Route::post('calls/dial', [CallController::class, 'dial']);
    Route::post('calls/hangup', [CallController::class, 'hangup']);
    Route::get('calls', [CallController::class, 'index']);
    Route::post('calls', [CallController::class, 'store']);
    
    Route::post('ImportCredits', [ImportController::class, 'importCredits']);
    Route::post('ImportClients', [ImportController::class, 'importClients']);
});