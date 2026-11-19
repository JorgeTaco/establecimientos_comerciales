<?php

namespace App\Http\Controllers\Web;

use App\Models\EncuestaImportada;
use App\Models\FilaEncuesta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EncuestaController
{
    public function index(): View
    {
        return view('admin.encuestas.index', [
            'encuestas' => EncuestaImportada::query()->select(['id', 'archivo', 'resumen', 'created_at'])
                ->latest('id')->simplePaginate(20),
        ]);
    }

    public function show(Request $request, EncuestaImportada $encuesta): View
    {
        $estado = $request->string('estado')->toString();
        $estado = array_key_exists($estado, FilaEncuesta::ESTADOS) ? $estado : '';
        $filas = $encuesta->filas()->select(['id', 'numero', 'nombre', 'estado', 'establecimiento_id'])
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->orderBy('numero')->simplePaginate(25)->appends(['estado' => $estado]);

        return view('admin.encuestas.show', ['encuesta' => $encuesta, 'filas' => $filas, 'estado' => $estado]);
    }

    public function fila(EncuestaImportada $encuesta, FilaEncuesta $fila): View
    {
        $encabezados = $encuesta->encabezados;
        $valores = $fila->celdas;
        $campos = [];
        for ($columna = 0; $columna < max(count($encabezados), count($valores)); $columna++) {
            $campos[] = [
                'nombre' => ($encabezados[$columna] ?? '') ?: 'Columna sin título '.($columna + 1),
                'valor' => $valores[$columna] ?? '',
            ];
        }

        return view('admin.encuestas.fila', compact('encuesta', 'fila', 'campos'));
    }
}
