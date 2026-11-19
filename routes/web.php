<?php

use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Listado de establecimientos (frontend). Vista en resources/views/establecimientos/.
Route::view('/establecimientos', 'establecimientos.index')->name('establecimientos.index');

Route::middleware('guest')->group(function () {
    Route::view('/iniciar-sesion', 'auth.iniciar-sesion')->name('login');
    Route::post('/iniciar-sesion', [SesionController::class, 'ingresar'])
        ->middleware('throttle:inicio-sesion')->name('login.store');
});

Route::post('/cerrar-sesion', [SesionController::class, 'salir'])->middleware('auth')->name('logout');
