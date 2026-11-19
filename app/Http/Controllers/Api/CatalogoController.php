<?php

namespace App\Http\Controllers\Api;

use App\Models\Producto;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogoController
{
    public function compartir(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $token = DB::transaction(function () use ($usuario) {
            User::query()->lockForUpdate()->findOrFail($usuario->id);
            $catalogo = DB::table('catalogos_compartidos')->where('user_id', $usuario->id);
            $token = $catalogo->value('token');
            if ($token === null) {
                $token = (string) Str::uuid();
                $catalogo->insert([
                    'user_id' => $usuario->id, 'token' => $token, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $token;
        });

        return response()->json(['url' => route('api.catalogos.publico', ['token' => $token])]);
    }

    public function revocar(Request $request): Response
    {
        DB::transaction(function () use ($request) {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            DB::table('catalogos_compartidos')->where('user_id', $request->user()->id)->delete();
        });

        return response()->noContent();
    }

    public function publico(string $token): JsonResponse
    {
        $usuario = User::query()->where('rol', 'emprendedor')->whereNull('suspendido_en')
            ->whereIn('id', DB::table('catalogos_compartidos')->where('token', $token)->select('user_id'))->firstOrFail();
        $productos = $usuario->productos()->whereNull('archivado_en')->orderBy('nombre')->orderBy('id')->get();

        return response()->json(['catalogo' => [
            'nombre' => $usuario->name,
            'productos' => $productos->map->only([
                'id', 'categoria_id', 'nombre', 'descripcion', 'foto_url', 'presentacion',
                'precio_comercio', 'precio_sugerido', 'pedido_minimo', 'capacidad_semanal',
                'vida_util_dias', 'requiere_refrigeracion', 'conservacion', 'zonas_entrega', 'frecuencia_entrega',
            ])->values(),
        ]])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function pdf(Request $request, Producto $producto): Response
    {
        abort_unless((int) $producto->user_id === (int) $request->user()->id && $producto->archivado_en === null, 404);
        $filas = [
            'Descripción' => $producto->descripcion,
            'Presentación' => $producto->presentacion,
            'Precio al comercio (USD)' => $producto->precio_comercio,
            'Precio sugerido (USD)' => $producto->precio_sugerido,
            'Pedido mínimo' => $producto->pedido_minimo,
            'Capacidad semanal' => $producto->capacidad_semanal,
            'Vida útil (días)' => $producto->vida_util_dias,
            'Requiere refrigeración' => $producto->requiere_refrigeracion ? 'Sí' : 'No',
            'Conservación' => $producto->conservacion,
            'Zonas de entrega' => implode(', ', $producto->zonas_entrega ?? []),
            'Frecuencia de entrega' => $producto->frecuencia_entrega,
        ];

        $html = '<!doctype html><html lang="es"><meta charset="utf-8"><body style="font-family: DejaVu Sans">';
        $html .= '<h1>'.e($producto->nombre).'</h1><dl>';
        foreach ($filas as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $html .= '<dt>'.e($etiqueta).'</dt><dd>'.e((string) $valor).'</dd>';
            }
        }
        $html .= '</dl></body></html>';

        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false]));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="producto-'.$producto->id.'.pdf"',
        ]);
    }
}
