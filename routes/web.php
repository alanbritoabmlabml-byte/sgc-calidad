<?php

use App\Http\Controllers\Admin\MaquinaController;
use App\Http\Controllers\Admin\ParametroController;
use App\Http\Controllers\Admin\PlantillaController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\GerenciaController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\LotController;
use App\Support\Permisos;
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
| La autorizacion es por permiso, no por rol: el rol solo define el juego
| inicial de permisos y el administrador lo ajusta casilla por casilla.
*/
Route::middleware('auth')->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/gerencia', GerenciaController::class)
        ->middleware('permiso:'.Permisos::TABLERO_GERENCIA)
        ->name('gerencia');

    // Campana de avisos: boletas sin emitir y lotes sin inspeccionar.
    Route::get('/avisos', [DashboardController::class, 'avisos'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_VER)
        ->name('avisos');

    // ----- Lotes -----
    Route::middleware('permiso:'.Permisos::LOTES_VER)->group(function () {
        Route::get('/lotes', [LotController::class, 'index'])->name('lotes.index');
        Route::get('/lotes/{lote}', [LotController::class, 'show'])->name('lotes.show');
    });

    Route::get('/lotes/nuevo/crear', [LotController::class, 'create'])
        ->middleware('permiso:'.Permisos::LOTES_CREAR)->name('lotes.create');
    Route::post('/lotes', [LotController::class, 'store'])
        ->middleware('permiso:'.Permisos::LOTES_CREAR)->name('lotes.store');
    Route::get('/lotes/{lote}/editar', [LotController::class, 'edit'])
        ->middleware('permiso:'.Permisos::LOTES_EDITAR)->name('lotes.edit');
    Route::put('/lotes/{lote}', [LotController::class, 'update'])
        ->middleware('permiso:'.Permisos::LOTES_EDITAR)->name('lotes.update');

    // ----- Inspecciones -----
    Route::middleware('permiso:'.Permisos::INSPECCIONES_VER)->group(function () {
        Route::get('/inspecciones', [InspectionController::class, 'index'])->name('inspecciones.index');
        Route::get('/inspecciones/{inspeccion}', [InspectionController::class, 'show'])->name('inspecciones.show');
    });

    Route::get('/lotes/{lote}/procesos/{proceso}/inspeccionar', [InspectionController::class, 'create'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_CREAR)->name('inspecciones.create');
    Route::post('/lotes/{lote}/procesos/{proceso}/inspeccionar', [InspectionController::class, 'store'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_CREAR)->name('inspecciones.store');

    Route::get('/inspecciones/{inspeccion}/editar', [InspectionController::class, 'edit'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_EDITAR)->name('inspecciones.edit');
    Route::put('/inspecciones/{inspeccion}', [InspectionController::class, 'update'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_EDITAR)->name('inspecciones.update');

    Route::post('/inspecciones/{inspeccion}/emitir', [InspectionController::class, 'publicar'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_EMITIR)->name('inspecciones.publicar');
    Route::delete('/inspecciones/{inspeccion}/emitir', [InspectionController::class, 'despublicar'])
        ->middleware('permiso:'.Permisos::INSPECCIONES_ANULAR)->name('inspecciones.despublicar');

    // ----- Etiquetas -----
    Route::middleware('permiso:'.Permisos::ETIQUETAS_IMPRIMIR)->group(function () {
        Route::get('/inspecciones/{inspeccion}/etiquetas', [EtiquetaController::class, 'deInspeccion'])
            ->name('inspecciones.etiquetas');
        Route::get('/lotes/{lote}/etiquetas', [EtiquetaController::class, 'deLote'])
            ->name('lotes.etiquetas');
    });

    /*
    |----------------------------------------------------------------------
    | Configuracion
    |----------------------------------------------------------------------
    | Aca vive la modularidad: procesos, plantillas de ensayo y parametros
    | se cargan como datos, sin tocar codigo.
    */
    Route::prefix('configuracion')->name('admin.')->group(function () {

        // ----- Plantillas de ensayo -----
        // Calidad puede ver y crear, pero no modificar ni eliminar: una
        // especificacion vigente no se retoca, se crea una revision nueva.
        //
        // OJO con el orden: /plantillas/nueva tiene que declararse antes de
        // /plantillas/{plantilla}, si no "nueva" se toma como identificador de
        // plantilla y la ruta responde 404.
        Route::get('/plantillas', [PlantillaController::class, 'index'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_VER)->name('plantillas.index');

        Route::middleware('permiso:'.Permisos::PLANTILLAS_CREAR)->group(function () {
            Route::get('/plantillas/nueva', [PlantillaController::class, 'create'])->name('plantillas.create');
            Route::post('/plantillas', [PlantillaController::class, 'store'])->name('plantillas.store');
            Route::post('/plantillas/{plantilla}/duplicar', [PlantillaController::class, 'duplicar'])->name('plantillas.duplicar');
            Route::post('/plantillas/{plantilla}/parametros', [ParametroController::class, 'store'])->name('parametros.store');
        });

        Route::get('/plantillas/{plantilla}', [PlantillaController::class, 'show'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_VER)->name('plantillas.show');

        Route::put('/plantillas/{plantilla}', [PlantillaController::class, 'update'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_EDITAR)->name('plantillas.update');
        Route::put('/parametros/{parametro}', [ParametroController::class, 'update'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_EDITAR)->name('parametros.update');
        Route::delete('/parametros/{parametro}', [ParametroController::class, 'destroy'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_ELIMINAR)->name('parametros.destroy');
        Route::post('/plantillas/{plantilla}/activar', [PlantillaController::class, 'activar'])
            ->middleware('permiso:'.Permisos::PLANTILLAS_ACTIVAR)->name('plantillas.activar');

        // ----- Productos -----
        Route::get('/productos', [ProductoController::class, 'index'])
            ->middleware('permiso:'.Permisos::CATALOGOS_VER)->name('productos.index');
        Route::get('/productos/create', [ProductoController::class, 'create'])
            ->middleware('permiso:'.Permisos::PRODUCTOS_CREAR)->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])
            ->middleware('permiso:'.Permisos::PRODUCTOS_CREAR)->name('productos.store');
        Route::get('/productos/{producto}/edit', [ProductoController::class, 'edit'])
            ->middleware('permiso:'.Permisos::PRODUCTOS_EDITAR)->name('productos.edit');
        Route::put('/productos/{producto}', [ProductoController::class, 'update'])
            ->middleware('permiso:'.Permisos::PRODUCTOS_EDITAR)->name('productos.update');

        // Alta rapida desde el formulario de lote, sin cambiar de pantalla.
        Route::post('/productos/rapido', [ProductoController::class, 'rapido'])
            ->middleware('permiso:'.Permisos::PRODUCTOS_CREAR)->name('productos.rapido');
        Route::post('/sectores/rapido', [SectorController::class, 'rapido'])
            ->middleware('permiso:'.Permisos::SECTORES_GESTIONAR)->name('sectores.rapido');

        // ----- Maquinas -----
        Route::get('/maquinas', [MaquinaController::class, 'index'])
            ->middleware('permiso:'.Permisos::CATALOGOS_VER)->name('maquinas.index');
        Route::resource('maquinas', MaquinaController::class)
            ->only(['create', 'store', 'edit', 'update'])
            ->middleware('permiso:'.Permisos::MAQUINAS_GESTIONAR);

        // ----- Sectores y procesos -----
        Route::resource('sectores', SectorController::class)
            ->except(['show', 'destroy'])
            ->middleware('permiso:'.Permisos::SECTORES_GESTIONAR)
            ->parameters(['sectores' => 'sector']);

        // ----- Usuarios -----
        Route::resource('usuarios', UsuarioController::class)
            ->except(['show', 'destroy'])
            ->middleware('permiso:'.Permisos::USUARIOS_GESTIONAR);
    });
});
