<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\FichaController;
use App\Http\Controllers\Web\ProductoController;
use App\Http\Controllers\Web\OportunidadController;

Route::get('/', function () {
    return view('welcome');
});

// Listado de establecimientos (frontend). Vista en resources/views/establecimientos/.
Route::view('/establecimientos', 'establecimientos.index')->name('establecimientos.index');

// Ficha pública de un comercio (llama a la API interna).
Route::get('/establecimientos/{id}', [FichaController::class, 'show'])->name('establecimientos.ficha');

// --- Autenticación (vistas web; la API gestiona las sesiones Laravel) ---
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('oportunidades.index');
    }
    return view('auth.login');
})->name('login');

Route::get('/registro', function () {
    if (auth()->check()) {
        return redirect()->route('oportunidades.index');
    }
    return view('auth.registro');
})->name('registro');

// --- Área autenticada ---
Route::middleware(['auth', \App\Http\Middleware\CuentaActiva::class])->group(function () {
    // --- Productos (emprendedor) ---
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.crear');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
    Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
    Route::patch('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy');
    Route::get('/productos/{producto}/compatibles', [ProductoController::class, 'compatibles'])->name('productos.compatibles');

    // --- Oportunidades y propuestas (emprendedor) ---
    Route::get('/oportunidades', function () {
        return view('oportunidades.index');
    })->name('oportunidades.index');

    Route::get('/oportunidades/crear/{establecimiento}', [OportunidadController::class, 'create'])->name('oportunidades.crear');
    Route::post('/oportunidades', [OportunidadController::class, 'store'])->name('oportunidades.store');
    Route::post('/establecimientos/{establecimiento}/propuestas', [OportunidadController::class, 'propuesta'])->name('oportunidades.propuesta');

    // Detalle de oportunidad (datos del comercio y propuestas se cargan desde la API en el frontend)
    Route::get('/oportunidades/{id}', function ($id) {
        $oportunidad = \App\Models\Oportunidad::query()
            ->where('user_id', auth()->id())
            ->where('id', $id)
            ->first();
        if (! $oportunidad) {
            abort(404, 'La oportunidad no existe.');
        }

        $estadosTono = [
            'por_contactar' => 'neutro',
            'contactado' => 'pendiente',
            'muestra_entregada' => 'pendiente',
            'en_negociacion' => 'pendiente',
            'primer_pedido' => 'positivo',
            'punto_venta_activo' => 'positivo',
            'no_concretada' => 'negativo',
        ];

        return view('oportunidades.show', [
            'oportunidadId' => $oportunidad->id,
            'establecimientoId' => $oportunidad->establecimiento_id,
            'estadoTono' => $estadosTono[$oportunidad->estado] ?? 'neutro',
            'estadoEtiqueta' => ucfirst(str_replace('_', ' ', $oportunidad->estado)),
            'notas' => $oportunidad->notas,
            'propuestas' => $oportunidad->propuestas,
            'propuestasCount' => $oportunidad->propuestas->count(),
        ]);
    })->name('oportunidades.show');

    Route::get('/propuestas-recibidas', function () {
        if (auth()->user()->rol !== 'comercio') {
            return redirect()->route('oportunidades.index');
        }
        return view('propuestas.recibidas');
    })->name('propuestas.recibidas');

    // Formulario para responder una propuesta (usa PATCH /api/propuestas-recibidas/{propuesta}/respuesta)
    Route::get('/propuestas-recibidas/{propuesta}/responder', function ($propuesta) {
        return view('propuestas.responder', [
            'propuestaId' => $propuesta,
        ]);
    })->name('propuestas.responder');

    Route::get('/comercio/establecimientos', function () {
        if (auth()->user()->rol !== 'comercio') {
            return redirect()->route('oportunidades.index');
        }
        return view('comercio.establecimientos');
    })->name('comercio.establecimientos');

    Route::get('/admin/indicadores', function () {
        if (auth()->user()->rol !== 'administrador') {
            return redirect()->route('oportunidades.index');
        }
        return view('admin.indicadores');
    })->name('admin.indicadores');

    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});
