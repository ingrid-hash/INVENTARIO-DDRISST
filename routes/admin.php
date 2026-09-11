<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RespaldoController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::middleware('permission:usuarios.ver')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/{usuario}/historial', [UserController::class, 'historial'])->name('usuarios.historial');
    });

    Route::middleware('permission:usuarios.crear')->group(function () {
        Route::get('usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
    });

    Route::middleware('permission:usuarios.editar')->group(function () {
        Route::get('usuarios/{usuario}/editar', [UserController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update');
        Route::patch('usuarios/{usuario}/estado', [UserController::class, 'toggleActivo'])->name('usuarios.estado');
        Route::patch('usuarios/{usuario}/desbloquear', [UserController::class, 'desbloquear'])->name('usuarios.desbloquear');
    });

    Route::middleware('permission:usuarios.resetear_password')
        ->patch('usuarios/{usuario}/resetear-password', [UserController::class, 'resetearPassword'])
        ->name('usuarios.resetear-password');

    Route::middleware('permission:usuarios.eliminar')
        ->delete('usuarios/{usuario}', [UserController::class, 'destroy'])
        ->name('usuarios.destroy');

    Route::middleware('permission:roles.ver')->get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::middleware('permission:roles.crear')->group(function () {
        Route::get('roles/crear', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    });
    Route::middleware('permission:roles.editar')->group(function () {
        Route::get('roles/{role}/editar', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('permission:roles.eliminar')
        ->delete('roles/{role}', [RoleController::class, 'destroy'])
        ->name('roles.destroy');

    Route::middleware('permission:permisos.ver')->get('permisos', [PermissionController::class, 'index'])->name('permisos.index');
    Route::middleware('permission:permisos.crear')->post('permisos', [PermissionController::class, 'store'])->name('permisos.store');
    Route::middleware('permission:permisos.editar')->put('permisos/{permission}', [PermissionController::class, 'update'])->name('permisos.update');
    Route::middleware('permission:permisos.eliminar')->delete('permisos/{permission}', [PermissionController::class, 'destroy'])->name('permisos.destroy');

    Route::middleware('permission:bitacora.ver')->get('bitacora', [AuditLogController::class, 'index'])->name('bitacora.index');

    // --- Respaldo de la base de datos
    Route::middleware('permission:respaldos.ver')->group(function () {
        Route::get('respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
        Route::get('respaldos/{nombre}', [RespaldoController::class, 'descargar'])->name('respaldos.descargar');
    });

    Route::middleware('permission:respaldos.crear')->group(function () {
        Route::post('respaldos', [RespaldoController::class, 'store'])->name('respaldos.store');
        Route::delete('respaldos/{nombre}', [RespaldoController::class, 'destroy'])->name('respaldos.destroy');
    });
});
