<?php

namespace App\Http\Controllers\Api;

use App\Models\Establecimiento;
use App\Models\PerfilComercial;
use App\Models\Propuesta;
use App\Support\FlujoComercial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropuestaController
{
    public function store(Request $request, Establecimiento $establecimiento): JsonResponse
    {
        $datos = $request->validate([
            'productos' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'productos.*' => ['required', 'integer', 'distinct'],
            'mensaje' => ['required', 'string', 'max:4000'],
            'referencia' => ['nullable', 'uuid'],
        ]);

        $propuesta = DB::transaction(function () use ($request, $establecimiento, $datos) {
            $establecimiento = Establecimiento::query()->lockForUpdate()->findOrFail($establecimiento->id);
            $oportunidad = $request->user()->oportunidades()->firstOrCreate(['establecimiento_id' => $establecimiento->id]);
            if (isset($datos['referencia'])) {
                $anterior = $oportunidad->propuestas()->where('referencia', $datos['referencia'])->first();
                if ($anterior !== null) {
                    $ids = array_map('intval', $datos['productos']);
                    if ($anterior->mensaje !== $datos['mensaje'] || array_column($anterior->productos, 'id') !== $ids) {
                        abort(409, 'La referencia ya pertenece a otra propuesta.');
                    }

                    return $anterior;
                }
            }

            abort_if($establecimiento->recepcion_proveedores === 'no_recibe_por_ahora', 409, 'El establecimiento no recibe propuestas por ahora.');
            $productos = $request->user()->productos()->whereNull('archivado_en')->whereKey($datos['productos'])->get()->keyBy('id');
            if ($productos->count() !== count($datos['productos'])) {
                throw ValidationException::withMessages(['productos' => 'Selecciona productos activos de tu propio catálogo.']);
            }

            return $oportunidad->propuestas()->create([
                'productos' => array_map(fn ($id) => $productos[$id]->only([
                    'id', 'nombre', 'presentacion', 'precio_comercio', 'pedido_minimo',
                ]), $datos['productos']),
                'mensaje' => $datos['mensaje'],
                'referencia' => $datos['referencia'] ?? null,
            ])->refresh();
        });

        return response()->json(['propuesta' => $propuesta], $propuesta->wasRecentlyCreated ? 201 : 200);
    }

    public function recibidas(Request $request): JsonResponse
    {
        $comercios = PerfilComercial::query()->where('user_id', $request->user()->id)->select('establecimiento_id');
        $propuestas = Propuesta::query()->whereHas('oportunidad', fn ($q) => $q->whereIn('establecimiento_id', $comercios))
            ->with('oportunidad:id,establecimiento_id')->latest('id')->get();

        return response()->json(['propuestas' => $propuestas->map(function (Propuesta $propuesta) {
            return $propuesta->attributesToArray() + ['establecimiento_id' => (string) $propuesta->oportunidad->establecimiento_id];
        })]);
    }

    public function responder(Request $request, Propuesta $propuesta): JsonResponse
    {
        $datos = $request->validate([
            'respuesta' => ['required', Rule::in(FlujoComercial::RESPUESTAS)],
            'mensaje_respuesta' => ['nullable', 'string', 'max:4000'],
        ]);
        $propuesta = DB::transaction(function () use ($request, $propuesta, $datos) {
            $perfil = PerfilComercial::query()->where('establecimiento_id', $propuesta->oportunidad->establecimiento_id)
                ->lockForUpdate()->first();
            abort_unless((int) $perfil?->user_id === (int) $request->user()->id, 404);
            $propuesta = Propuesta::query()->lockForUpdate()->findOrFail($propuesta->id);
            abort_if($propuesta->respondida_en !== null, 409, 'Esta propuesta ya tiene respuesta.');
            $propuesta->update($datos + ['respondida_en' => now()]);

            return $propuesta;
        });

        return response()->json(['propuesta' => $propuesta]);
    }
}
