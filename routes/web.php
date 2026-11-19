<?php

use App\Http\Controllers\SesionController;
use App\Http\Controllers\Web\ComercioController;
use App\Http\Controllers\Web\EncuestaController;
use App\Http\Controllers\Web\FichaController;
use App\Http\Controllers\Web\OportunidadController;
use App\Http\Controllers\Web\ProductoController;
use App\Http\Middleware\CuentaActiva;
use Illuminate\Support\Facades\Route;

Route::view('/', 'establecimientos.index')->name('inicio');
Route::view('/evaluacion', 'evaluacion')->name('evaluacion');
Route::view('/establecimientos', 'establecimientos.index')->name('establecimientos.index');
Route::get('/establecimientos/{establecimiento}', [FichaController::class, 'show'])
    ->whereNumber('establecimiento')->name('establecimientos.ficha');
Route::get('/catalogos/{token}', [ProductoController::class, 'catalogo'])->whereUuid('token')->name('catalogos.publico');

Route::middleware('guest')->group(function () {
    Route::redirect('/login', '/iniciar-sesion');
    Route::view('/iniciar-sesion', 'auth.login')->name('login');
    Route::post('/iniciar-sesion', [SesionController::class, 'ingresar'])
        ->middleware('throttle:inicio-sesion')->name('login.store');
    Route::view('/registro', 'auth.registro')->name('registro');
    Route::post('/registro', [SesionController::class, 'registrar'])->middleware('throttle:5,1')->name('registro.store');
});

Route::post('/cerrar-sesion', [SesionController::class, 'salir'])->middleware('auth')->name('logout');

Route::middleware(['auth', CuentaActiva::class])->group(function () {
    Route::post('/establecimientos/{establecimiento}/reportar', [FichaController::class, 'reportar'])
        ->whereNumber('establecimiento')->middleware('throttle:10,1')->name('establecimientos.reportar');
});

Route::middleware(['auth', CuentaActiva::class.':emprendedor'])->group(function () {
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.crear');
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->whereNumber('producto')->name('productos.show');
    Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])->whereNumber('producto')->name('productos.edit');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->whereNumber('producto')->name('productos.destroy');
    Route::get('/productos/{producto}/compatibles', [ProductoController::class, 'compatibles'])
        ->whereNumber('producto')->name('productos.compatibles');
    Route::post('/catalogo/compartir', [ProductoController::class, 'compartir'])->name('catalogo.compartir');
    Route::delete('/catalogo/compartir', [ProductoController::class, 'revocar'])->name('catalogo.revocar');

    Route::view('/oportunidades', 'oportunidades.index')->name('oportunidades.index');
    Route::get('/oportunidades/crear/{establecimiento}', [OportunidadController::class, 'create'])
        ->whereNumber('establecimiento')->name('oportunidades.crear');
    Route::post('/oportunidades', [OportunidadController::class, 'store'])->name('oportunidades.store');
    Route::get('/oportunidades/{oportunidad}', [OportunidadController::class, 'show'])
        ->whereNumber('oportunidad')->name('oportunidades.show');
    Route::patch('/oportunidades/{oportunidad}', [OportunidadController::class, 'update'])
        ->whereNumber('oportunidad')->name('oportunidades.update');
});

Route::middleware(['auth', CuentaActiva::class.':comercio'])->group(function () {
    Route::view('/propuestas-recibidas', 'propuestas.recibidas')->name('propuestas.recibidas');
    Route::get('/propuestas-recibidas/{propuesta}/responder', [OportunidadController::class, 'responder'])
        ->whereNumber('propuesta')->name('propuestas.responder');
    Route::view('/comercio/establecimientos', 'comercio.establecimientos')->name('comercio.establecimientos');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', CuentaActiva::class.':administrador'])->group(function () {
    Route::view('/indicadores', 'admin.indicadores')->name('indicadores');
    Route::get('/encuestas', [EncuestaController::class, 'index'])->name('encuestas.index');
    Route::get('/encuestas/{encuesta}', [EncuestaController::class, 'show'])->whereNumber('encuesta')->name('encuestas.show');
    Route::get('/encuestas/{encuesta}/filas/{fila}', [EncuestaController::class, 'fila'])
        ->whereNumber(['encuesta', 'fila'])->scopeBindings()->name('encuestas.filas.show');
});

Route::middleware(['auth', CuentaActiva::class.':comercio,administrador'])->group(function () {
    Route::get('/comercio/establecimientos/crear', [ComercioController::class, 'create'])->name('comercio.crear');
    Route::post('/comercio/establecimientos', [ComercioController::class, 'store'])->name('comercio.store');
    Route::get('/comercio/establecimientos/{establecimiento}/editar', [ComercioController::class, 'edit'])
        ->whereNumber('establecimiento')->name('comercio.editar');
    Route::patch('/comercio/establecimientos/{establecimiento}', [ComercioController::class, 'update'])
        ->whereNumber('establecimiento')->name('comercio.update');
});
Route::post('/admin/establecimientos/{establecimiento}/verificar', [ComercioController::class, 'verificar'])
    ->whereNumber('establecimiento')->middleware(['auth', CuentaActiva::class.':administrador'])->name('admin.verificar');
