<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlockchainController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FuelConsumptionController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MileageRecordController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'project' => config('autochain.name'),
        'author' => config('autochain.author'),
    ]));

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/wallet/nonce', [AuthController::class, 'walletNonce']);
    Route::post('/auth/wallet', [AuthController::class, 'loginWallet']);

    Route::middleware(['auth:sanctum', 'auditor.readonly'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard', [VehicleController::class, 'dashboard']);

        // Lecture ouverte à tous les rôles authentifiés
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::get('/vehicles/{vehicle}/timeline', [VehicleController::class, 'timeline']);
        Route::get('/vehicles/{vehicle}/blockchain', [BlockchainController::class, 'vehicleProof']);
        Route::get('/vehicles/{vehicle}/consumption/average', [FuelConsumptionController::class, 'average']);

        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::get('/mileage-records', [MileageRecordController::class, 'index']);
        Route::get('/maintenances', [MaintenanceController::class, 'index']);
        Route::get('/maintenances/{maintenance}', [MaintenanceController::class, 'show']);
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::get('/documents/{document}/verify', [DocumentController::class, 'verify']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::get('/fuel-consumptions', [FuelConsumptionController::class, 'index']);
        Route::get('/blockchain/transactions', [BlockchainController::class, 'index']);
        Route::get('/blockchain/settings', [BlockchainController::class, 'settings']);
        Route::get('/blockchain/status', [BlockchainController::class, 'status']);
        Route::get('/blockchain/transactions/{transaction}/calldata', [BlockchainController::class, 'calldata']);

        // Liste des chauffeurs pour affectations
        Route::middleware('role:gestionnaire,super_admin')->get('/drivers', [UserController::class, 'drivers']);

        // Super admin — gestion des comptes
        Route::middleware('role:super_admin')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::put('/blockchain/settings', [BlockchainController::class, 'updateSettings']);
        });


        // Gestionnaire + Super admin
        Route::middleware('role:gestionnaire,super_admin')->group(function () {
            Route::post('/vehicles', [VehicleController::class, 'store']);
            Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
            Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

            Route::post('/assignments', [AssignmentController::class, 'store']);
            Route::post('/assignments/{assignment}/close', [AssignmentController::class, 'close']);

            Route::post('/documents', [DocumentController::class, 'store']);
            Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

            Route::post('/alerts/generate', [AlertController::class, 'generate']);
            Route::post('/alerts/{alert}/read', [AlertController::class, 'markRead']);
            Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);

            Route::post('/blockchain/prepare', [BlockchainController::class, 'prepare']);
            Route::post('/blockchain/transactions/{transaction}/sign-admin', [BlockchainController::class, 'signAdmin']);
            Route::post('/blockchain/transactions/{transaction}/confirm', [BlockchainController::class, 'confirm']);
            Route::post('/blockchain/transactions/{transaction}/anchor', [BlockchainController::class, 'anchor']);
            Route::post('/blockchain/transactions/{transaction}/simulate', [BlockchainController::class, 'simulate']);
        });

        // Chauffeur
        Route::middleware('role:chauffeur,gestionnaire,super_admin')->group(function () {
            Route::post('/assignments/{assignment}/acknowledge', [AssignmentController::class, 'acknowledge']);
            Route::post('/mileage-records', [MileageRecordController::class, 'store']);
            Route::post('/fuel-consumptions', [FuelConsumptionController::class, 'store']);
        });

        // Garagiste agréé
        Route::middleware('role:garagiste,gestionnaire,super_admin')->group(function () {
            Route::post('/maintenances', [MaintenanceController::class, 'store']);
        });

        // Auditeur / acheteur — signature sensible
        Route::middleware('role:auditeur,super_admin')->group(function () {
            Route::post('/blockchain/transactions/{transaction}/sign-buyer', [BlockchainController::class, 'signBuyer']);
        });
    });
});
