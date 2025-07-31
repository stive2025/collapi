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
use Illuminate\Support\Facades\Route;

// Authentication
Route::post('/login',[LoginController::class,'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('/users',UserController::class);
    Route::apiResource('/credits',CreditController::class);
    Route::apiResource('/clients',ClientController::class);
    Route::apiResource('/managements',ManagementController::class);
    Route::apiResource('/payments',CollectionPaymentController::class);
    Route::apiResource('/businesses',BusinessController::class);
    Route::apiResource('/campains',CampainController::class);
    
    // Para utilidades asignación de campaña
    // Route::post('/assign',[CampainAssignController::class,'assign']);
    // Route::post('/assign',[CampainAssignController::class,'assign']);

    Route::get('/calls',[CallController::class,'index']);
    Route::post('/calls/dial',[CallController::class,'dial']);
    Route::post('/calls/hangup',[CallController::class,'hangup']);
    Route::post('/calls',[CallController::class,'store']);
});