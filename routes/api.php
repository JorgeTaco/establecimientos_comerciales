<?php

use App\Http\Controllers\Api\AdministracionController;
use App\Http\Controllers\Api\BusquedaEstablecimientoController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\EstablecimientoController;
use App\Http\Controllers\Api\FichaEstablecimientoController;
use App\Http\Controllers\Api\FotoController;
use App\Http\Controllers\Api\ImportarArchivoController;
use App\Http\Controllers\Api\OportunidadController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\PropuestaController;
use App\Http\Controllers\SesionController;
use App\Http\Middleware\CuentaActiva;
use Illuminate\Support\Facades\Route;

Route::get('/establecimientos', EstablecimientoController::class)->name('api.establecimientos.index');
Route::get('/establecimientos/buscar', [BusquedaEstablecimientoController::class, 'buscar']);
Route::get('/establecimientos/{establecimiento}', [FichaEstablecimientoController::class, 'show'])->whereNumber('establecimiento');
Route::get('/categorias', [AdministracionController::class, 'categorias']);
Route::get('/catalogos/{token}', [CatalogoController::class, 'publico'])->whereUuid('token')->name('api.catalogos.publico');

Route::middleware('web')->group(function () {
    Route::get('/sesion/csrf', [SesionController::class, 'csrf'])->middleware('throttle:contexto-sesion');
    Route::get('/sesion/contexto', [SesionController::class, 'contexto'])->middleware('throttle:contexto-sesion');
    Route::post('/registro', [SesionController::class, 'registrar'])->middleware('throttle:5,1');
    Route::post('/sesion', [SesionController::class, 'ingresar'])->middleware('throttle:inicio-sesion');

    Route::middleware('auth')->group(function () {
        Route::delete('/sesion', [SesionController::class, 'salir']);
        Route::get('/sesion', [SesionController::class, 'usuario'])->middleware(CuentaActiva::class);
        Route::post('/fotos', FotoController::class)->middleware(CuentaActiva::class, 'throttle:10,1');
        Route::post('/establecimientos/{establecimiento}/reportes', [AdministracionController::class, 'reporte'])
            ->middleware(CuentaActiva::class, 'throttle:10,1')->whereNumber('establecimiento');
    });

    Route::middleware(['auth', CuentaActiva::class.':emprendedor'])->group(function () {
        Route::apiResource('productos', ProductoController::class)
            ->parameters(['productos' => 'producto'])->names('api.productos');
        Route::get('/productos/{producto}/establecimientos-compatibles', [BusquedaEstablecimientoController::class, 'compatibles'])->whereNumber('producto');
        Route::get('/productos/{producto}/ficha.pdf', [CatalogoController::class, 'pdf'])->middleware('throttle:10,1')->whereNumber('producto');
        Route::post('/catalogo/compartir', [CatalogoController::class, 'compartir']);
        Route::delete('/catalogo/compartir', [CatalogoController::class, 'revocar']);
        Route::get('/oportunidades', [OportunidadController::class, 'index']);
        Route::post('/oportunidades', [OportunidadController::class, 'store']);
        Route::get('/oportunidades/{oportunidad}', [OportunidadController::class, 'show'])->whereNumber('oportunidad');
        Route::patch('/oportunidades/{oportunidad}', [OportunidadController::class, 'update'])->whereNumber('oportunidad');
        Route::get('/oportunidades/{oportunidad}/propuestas', [OportunidadController::class, 'propuestas'])->whereNumber('oportunidad');
        Route::post('/establecimientos/{establecimiento}/propuestas', [PropuestaController::class, 'store'])->whereNumber('establecimiento');
    });

    Route::middleware(['auth', CuentaActiva::class.':comercio,administrador'])->group(function () {
        Route::get('/mis-establecimientos', [FichaEstablecimientoController::class, 'propios']);
        Route::get('/mis-establecimientos/{establecimiento}', [FichaEstablecimientoController::class, 'privada'])->whereNumber('establecimiento');
        Route::post('/establecimientos', [FichaEstablecimientoController::class, 'store']);
        Route::patch('/establecimientos/{establecimiento}', [FichaEstablecimientoController::class, 'update'])->whereNumber('establecimiento');
    });

    Route::middleware(['auth', CuentaActiva::class.':comercio'])->group(function () {
        Route::get('/propuestas-recibidas', [PropuestaController::class, 'recibidas']);
        Route::get('/propuestas-recibidas/{propuesta}', [PropuestaController::class, 'recibida'])->whereNumber('propuesta');
        Route::patch('/propuestas-recibidas/{propuesta}/respuesta', [PropuestaController::class, 'responder'])->whereNumber('propuesta');
    });

    Route::prefix('/admin')->middleware(['auth', CuentaActiva::class.':administrador'])->group(function () {
        Route::get('/categorias', [AdministracionController::class, 'todasCategorias']);
        Route::post('/categorias', [AdministracionController::class, 'guardarCategoria']);
        Route::patch('/categorias/{categoria}', [AdministracionController::class, 'guardarCategoria'])->whereNumber('categoria');
        Route::get('/usuarios', [AdministracionController::class, 'usuarios']);
        Route::patch('/usuarios/{usuario}', [AdministracionController::class, 'actualizarUsuario'])->whereNumber('usuario');
        Route::get('/reportes', [AdministracionController::class, 'reportes']);
        Route::patch('/reportes/{reporte}', [AdministracionController::class, 'resolver'])->whereNumber('reporte');
        Route::get('/indicadores', [AdministracionController::class, 'indicadores']);
        Route::post('/establecimientos/importar', [AdministracionController::class, 'importacion'])->middleware('throttle:10,1');
        Route::post('/establecimientos/importar-archivo', ImportarArchivoController::class)->middleware('throttle:5,1');
        Route::post('/establecimientos/duplicados', [FichaEstablecimientoController::class, 'duplicados'])->middleware('throttle:30,1');
        Route::post('/establecimientos/{establecimiento}/verificar', [AdministracionController::class, 'verificar'])->whereNumber('establecimiento');
        Route::patch('/establecimientos/{establecimiento}/responsable', [AdministracionController::class, 'asignar'])->whereNumber('establecimiento');
    });
});
