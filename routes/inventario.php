<?php

use App\Http\Controllers\Inventario\BienController;
use App\Http\Controllers\Inventario\EmpleadoController;
use App\Http\Controllers\Inventario\ImportacionController;
use App\Http\Controllers\Inventario\RenglonController;
use App\Http\Controllers\Inventario\TarjetaController;
use App\Http\Controllers\Inventario\UnidadServicioController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('inventario')->name('inventario.')->group(function () {

    // --- Bienes
    Route::middleware('permission:bienes.ver')->group(function () {
        Route::get('bienes', [BienController::class, 'index'])->name('bienes.index');
        Route::get('bienes/{bien}', [BienController::class, 'show'])->name('bienes.show');
    });

    Route::middleware('permission:bienes.crear')->group(function () {
        Route::get('bienes-nuevo', [BienController::class, 'create'])->name('bienes.create');
        Route::post('bienes', [BienController::class, 'store'])->name('bienes.store');
    });

    Route::middleware('permission:bienes.editar')->group(function () {
        Route::get('bienes/{bien}/editar', [BienController::class, 'edit'])->name('bienes.edit');
        Route::put('bienes/{bien}', [BienController::class, 'update'])->name('bienes.update');
    });

    
    Route::middleware('permission:bienes.asignar_cuenta')
        ->patch('bienes-cuenta', [BienController::class, 'asignarCuenta'])
        ->name('bienes.asignar-cuenta');

    Route::middleware('permission:bienes.eliminar')
        ->delete('bienes/{bien}', [BienController::class, 'destroy'])
        ->name('bienes.destroy');

    // --- Cuentas (renglones presupuestarios)
    Route::middleware('permission:renglones.ver')
        ->get('cuentas', [RenglonController::class, 'index'])->name('renglones.index');
    Route::middleware('permission:renglones.crear')
        ->post('cuentas', [RenglonController::class, 'store'])->name('renglones.store');
    Route::middleware('permission:renglones.editar')
        ->put('cuentas/{renglon}', [RenglonController::class, 'update'])->name('renglones.update');
    Route::middleware('permission:renglones.eliminar')
        ->delete('cuentas/{renglon}', [RenglonController::class, 'destroy'])->name('renglones.destroy');

    // --- Empleados responsables de bienes
    Route::middleware('permission:empleados.ver')
        ->get('empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
    Route::middleware('permission:empleados.crear')
        ->post('empleados', [EmpleadoController::class, 'store'])->name('empleados.store');
    Route::middleware('permission:empleados.editar')
        ->put('empleados/{empleado}', [EmpleadoController::class, 'update'])->name('empleados.update');
    Route::middleware('permission:empleados.eliminar')
        ->delete('empleados/{empleado}', [EmpleadoController::class, 'destroy'])->name('empleados.destroy');

    // --- Tarjetas de responsabilidad
    Route::middleware('permission:tarjetas.ver')->group(function () {
        Route::get('tarjetas', [TarjetaController::class, 'index'])->name('tarjetas.index');
        Route::get('tarjetas/{tarjeta}', [TarjetaController::class, 'show'])->name('tarjetas.show');
    });

    Route::middleware('permission:tarjetas.crear')
        ->post('tarjetas', [TarjetaController::class, 'store'])->name('tarjetas.store');

    Route::middleware('permission:tarjetas.editar')->group(function () {
        Route::post('tarjetas/{tarjeta}/bienes', [TarjetaController::class, 'agregarBien'])
            ->name('tarjetas.agregar-bien');
        Route::delete('tarjetas/{tarjeta}/bienes', [TarjetaController::class, 'quitarBien'])
            ->name('tarjetas.quitar-bien');
    });

    Route::middleware('permission:tarjetas.regenerar')
        ->post('tarjetas/{tarjeta}/regenerar', [TarjetaController::class, 'regenerar'])
        ->name('tarjetas.regenerar');

    Route::middleware('permission:tarjetas.imprimir')->group(function () {
        Route::get('tarjetas/{tarjeta}/imprimir', [TarjetaController::class, 'imprimir'])
            ->name('tarjetas.imprimir');
        Route::post('tarjetas/{tarjeta}/impresion', [TarjetaController::class, 'marcarImpreso'])
            ->name('tarjetas.impresion');

        // Calce: alinear la impresion con el papel que ya salio impreso.
        Route::get('tarjetas/{tarjeta}/calce', [TarjetaController::class, 'calce'])
            ->name('tarjetas.calce');
        Route::post('tarjetas/{tarjeta}/calce', [TarjetaController::class, 'guardarCalce'])
            ->name('tarjetas.calce.guardar');
        Route::post('tarjetas/{tarjeta}/hoja', [TarjetaController::class, 'cambiarEstadoHoja'])
            ->name('tarjetas.hoja.estado');
        Route::post('tarjetas/{tarjeta}/renglones/{renglon}/hoja', [TarjetaController::class, 'moverRenglon'])
            ->name('tarjetas.renglon.mover');
    });

    // Retractar la marca de impresion de un bien: correccion de control interno,
    // con justificacion obligatoria, por eso va con permiso aparte.
    Route::middleware('permission:tarjetas.desmarcar_impresion')
        ->post('tarjetas/{tarjeta}/renglones/{renglon}/desmarcar', [TarjetaController::class, 'desmarcarImpresion'])
        ->name('tarjetas.renglon.desmarcar');

    // --- Importacion desde Excel
    Route::middleware('permission:importaciones.ver')->group(function () {
        Route::get('importacion', [ImportacionController::class, 'index'])->name('importacion.index');
        Route::get('importacion/{importacion}', [ImportacionController::class, 'show'])->name('importacion.show');
    });

    Route::middleware('permission:importaciones.ejecutar')->group(function () {
        Route::post('importacion/subir', [ImportacionController::class, 'subir'])->name('importacion.subir');
        Route::get('importacion-configurar', [ImportacionController::class, 'configurar'])
            ->name('importacion.configurar');
        Route::post('importacion-previsualizar', [ImportacionController::class, 'previsualizar'])
            ->name('importacion.previsualizar');
        Route::post('importacion-ejecutar', [ImportacionController::class, 'ejecutar'])
            ->name('importacion.ejecutar');
    });

    Route::middleware('permission:importaciones.revertir')
        ->post('importacion/{importacion}/revertir', [ImportacionController::class, 'revertir'])
        ->name('importacion.revertir');

    // --- Unidades de servicio
    Route::middleware('permission:unidades.ver')
        ->get('unidades', [UnidadServicioController::class, 'index'])->name('unidades.index');
    Route::middleware('permission:unidades.crear')
        ->post('unidades', [UnidadServicioController::class, 'store'])->name('unidades.store');
    Route::middleware('permission:unidades.editar')
        ->put('unidades/{unidad}', [UnidadServicioController::class, 'update'])->name('unidades.update');
    Route::middleware('permission:unidades.eliminar')
        ->delete('unidades/{unidad}', [UnidadServicioController::class, 'destroy'])->name('unidades.destroy');
});
