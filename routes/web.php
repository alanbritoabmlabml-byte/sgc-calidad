<?php

use App\Http\Controllers\Admin\MaquinaController;
use App\Http\Controllers\Admin\ParametroController;
use App\Http\Controllers\Admin\PlantillaController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publico
|--------------------------------------------------------------------------
| Destino del QR impreso en la etiqueta. Sin login: lo consulta tanto el
| personal interno como el cliente. La ruta es corta a proposito, para que
| el QR quede con menos densidad y se lea mejor desde una etiqueta chica.
*/
Route::get('/c/{token}', [CertificadoController::class, 'show'])
    ->name('certificado')
    ->where('token', '[A-Za-z0-9]{8,32}');

/*
|--------------------------------------------------------------------------
| Autenticacion
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Sistema
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    // ----- Lotes -----
    Route::get('/lotes', [LotController::class, 'index'])->name('lotes.index');
    Route::get('/lotes/{lote}', [LotController::class, 'show'])->name('lotes.show');
    Route::get('/lotes/{lote}/etiquetas', [EtiquetaController::class, 'deLote'])->name('lotes.etiquetas');

    // ----- Inspecciones -----
    Route::get('/inspecciones', [InspectionController::class, 'index'])->name('inspecciones.index');
    Route::get('/inspecciones/{inspeccion}', [InspectionController::class, 'show'])->name('inspecciones.show');
    Route::get('/inspecciones/{inspeccion}/etiquetas', [EtiquetaController::class, 'deInspeccion'])->name('inspecciones.etiquetas');

    // ----- Escritura: admin y calidad -----
    Route::middleware('editor')->group(function () {
        Route::get('/lotes/nuevo/crear', [LotController::class, 'create'])->name('lotes.create');
        Route::post('/lotes', [LotController::class, 'store'])->name('lotes.store');
        Route::get('/lotes/{lote}/editar', [LotController::class, 'edit'])->name('lotes.edit');
        Route::put('/lotes/{lote}', [LotController::class, 'update'])->name('lotes.update');

        Route::get('/lotes/{lote}/procesos/{proceso}/inspeccionar', [InspectionController::class, 'create'])
            ->name('inspecciones.create');
        Route::post('/lotes/{lote}/procesos/{proceso}/inspeccionar', [InspectionController::class, 'store'])
            ->name('inspecciones.store');

        Route::get('/inspecciones/{inspeccion}/editar', [InspectionController::class, 'edit'])->name('inspecciones.edit');
        Route::put('/inspecciones/{inspeccion}', [InspectionController::class, 'update'])->name('inspecciones.update');

        Route::post('/inspecciones/{inspeccion}/emitir', [InspectionController::class, 'publicar'])->name('inspecciones.publicar');
        Route::delete('/inspecciones/{inspeccion}/emitir', [InspectionController::class, 'despublicar'])->name('inspecciones.despublicar');
    });

    /*
    |----------------------------------------------------------------------
    | Configuracion (solo administrador)
    |----------------------------------------------------------------------
    | Aca vive la modularidad: procesos, plantillas de ensayo y parametros
    | se cargan como datos, sin tocar codigo.
    */
    Route::middleware('admin')->prefix('configuracion')->name('admin.')->group(function () {

        Route::get('/plantillas', [PlantillaController::class, 'index'])->name('plantillas.index');
        Route::get('/plantillas/nueva', [PlantillaController::class, 'create'])->name('plantillas.create');
        Route::post('/plantillas', [PlantillaController::class, 'store'])->name('plantillas.store');
        Route::get('/plantillas/{plantilla}', [PlantillaController::class, 'show'])->name('plantillas.show');
        Route::put('/plantillas/{plantilla}', [PlantillaController::class, 'update'])->name('plantillas.update');
        Route::post('/plantillas/{plantilla}/activar', [PlantillaController::class, 'activar'])->name('plantillas.activar');
        Route::post('/plantillas/{plantilla}/duplicar', [PlantillaController::class, 'duplicar'])->name('plantillas.duplicar');

        Route::post('/plantillas/{plantilla}/parametros', [ParametroController::class, 'store'])->name('parametros.store');
        Route::put('/parametros/{parametro}', [ParametroController::class, 'update'])->name('parametros.update');
        Route::delete('/parametros/{parametro}', [ParametroController::class, 'destroy'])->name('parametros.destroy');

        Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
        Route::resource('maquinas', MaquinaController::class)->except(['show', 'destroy']);
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    });
});
