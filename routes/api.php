<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MealTicketController;
use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MealSessionController;
use App\Http\Controllers\Api\EventPassController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RiskProfileController;
use App\Http\Controllers\Api\ScreeningVisitController;
use App\Http\Controllers\Api\TicketQrController;
use App\Http\Controllers\ScannerController as ControllersScannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:api', 'facility.scope'])->group(function () {
Route::apiResource('meals', MealController::class);
    Route::patch('/meals/{meal}/status', [MealController::class, 'updateStatus']);

    Route::post('/meals/{meal}/generate-tickets', [MealTicketController::class, 'generate']);
    Route::get('/meals/{meal}/tickets', [MealTicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [MealTicketController::class, 'show']);
    Route::patch('/tickets/{ticket}/void', [MealTicketController::class, 'void']);

    Route::get('/tickets/{ticket}/qr', [TicketQrController::class, 'show']);
    Route::post('/tickets/{ticket}/qr/regenerate', [TicketQrController::class, 'regenerate']);
    Route::get('/tickets/{ticket}/qr/download', [TicketQrController::class, 'download']);

    Route::get('/meals/{meal}/tickets/download-zip', [MealTicketController::class, 'downloadZip']);
    Route::get('/meals/{meal}/tickets/download-pdf', [MealTicketController::class, 'downloadPdf']);

    Route::post('/scanner/validate', [ScannerController::class, 'validateTicket']);
    Route::post('/scanner/redeem', [ScannerController::class, 'redeem']);

    Route::get('/meals/{meal}/summary', [ReportController::class, 'summary']);
    Route::get('/meals/{meal}/scan-logs', [ReportController::class, 'scanLogs']);

    Route::apiResource('events', EventController::class);

    Route::get('/events/{event}/meal-sessions', [MealSessionController::class, 'index']);
    Route::post('/events/{event}/meal-sessions', [MealSessionController::class, 'store']);
    Route::get('/meal-sessions/{mealSession}', [MealSessionController::class, 'show']);
    Route::put('/meal-sessions/{mealSession}', [MealSessionController::class, 'update']);
    Route::patch('/meal-sessions/{mealSession}/status', [MealSessionController::class, 'updateStatus']);
    Route::delete('/meal-sessions/{mealSession}', [MealSessionController::class, 'destroy']);

    Route::get('/events/{event}/passes', [EventPassController::class, 'index']);
    Route::post('/events/{event}/generate-passes', [EventPassController::class, 'generate']);
    Route::get('/passes/{pass}', [EventPassController::class, 'show']);
    Route::patch('/passes/{pass}/void', [EventPassController::class, 'void']);
    Route::get('/passes/{pass}/qr', [EventPassController::class, 'qr']);
    Route::get('/passes/{pass}/qr/download', [EventPassController::class, 'downloadQr']);

Route::post('/scanner/redeem', [ScannerController::class, 'redeem']);
    Route::get('/events/{event}/passes/download-pdf', [EventPassController::class, 'downloadPdf']);

});