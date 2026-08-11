<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\PasswordAssistanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');

   
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('olvide-password', [PasswordAssistanceController::class, 'show'])
        ->name('password.ayuda');
});

Route::middleware('auth')->group(function () {
    Route::get('cambio-obligatorio', [ForcePasswordChangeController::class, 'create'])
        ->name('password.forzado');

    Route::post('cambio-obligatorio', [ForcePasswordChangeController::class, 'store'])
        ->name('password.forzado.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
