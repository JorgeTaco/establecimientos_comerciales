<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportarArchivoController
{
    public function __invoke(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'max:1024', 'extensions:xlsx,csv'],
            'simular' => ['required', 'boolean'],
        ]);
        $archivo = $datos['archivo'];
        $directorio = 'importaciones/web/'.Str::uuid();
        $nombre = 'encuesta.'.strtolower($archivo->getClientOriginalExtension());
        $almacen = Storage::disk('local');

        try {
            $ruta = $almacen->putFileAs($directorio, $archivo, $nombre);
            abort_if($ruta === false, 500, 'No se pudo almacenar el archivo temporal.');
            $reporte = $directorio.'/reporte.json';
            Artisan::call('establecimientos:importar', [
                'archivo' => $almacen->path($ruta),
                '--carga-web' => true,
                '--dry-run' => (bool) $datos['simular'],
                '--json' => $almacen->path($reporte),
            ]);

            if (! $almacen->exists($reporte)) {
                throw ValidationException::withMessages([
                    'archivo' => 'No se pudo procesar el archivo. Comprueba el formato, los encabezados y los límites de carga.',
                ]);
            }

            $resultado = json_decode($almacen->get($reporte), true, flags: JSON_THROW_ON_ERROR);

            return response()->json([
                'simulacion' => (bool) $datos['simular'],
                'resumen' => $resultado['resumen'],
                'filas' => array_map(
                    fn (array $fila) => ['fila' => $fila['fila'], 'estado' => $fila['estado']],
                    $resultado['filas']
                ),
            ]);
        } finally {
            $almacen->deleteDirectory($directorio);
        }
    }
}
