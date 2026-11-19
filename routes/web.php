<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Listado de establecimientos (frontend). Vista en resources/views/establecimientos/.
Route::view('/establecimientos', 'establecimientos.index')->name('establecimientos.index');
