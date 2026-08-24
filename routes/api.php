<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TicketTypeController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::apiResource('events', EventController::class)->only([
    'index',
    'show',
]);

Route::get('/events/{event}/ticket-types', [TicketTypeController::class, 'index']);
Route::get('/ticket-types/{ticketType}', [TicketTypeController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::apiResource('events', EventController::class)->only([
        'store',
        'update',
        'destroy',
    ]);

    Route::apiResource('ticket-types', TicketTypeController::class)->only([
        'store',
        'update',
        'destroy',
    ]);

    Route::apiResource('orders', OrderController::class)->only([
        'index',
        'store',
        'show',
        'update',
    ]);
});
