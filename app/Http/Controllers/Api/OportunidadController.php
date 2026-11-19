<?php

namespace App\Http\Controllers\Api;

use App\Models\Oportunidad;
use App\Support\CacheComercial;
use App\Support\FlujoComercial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OportunidadController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'oportunidades' => $request->user()->oportunidades()
                ->with('establecimiento:id,nombre,parroquia')->latest('updated_at')->latest('id')->get(),
        ]);
    }

    public function show(Request $request, Oportunidad $oportunidad): JsonResponse
    {
        abort_unless((int) $oportunidad->user_id === (int) $request->user()->id, 404);

        return response()->json(['oportunidad' => $oportunidad->load([
            'establecimiento:id,nombre,parroquia',
            'propuestas' => fn ($propuestas) => $propuestas->latest('id'),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
        ]);
        $oportunidad = $request->user()->oportunidades()->firstOrCreate($datos);

        return response()->json([
            'oportunidad' => $oportunidad->refresh(),
        ], $oportunidad->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, Oportunidad $oportunidad): JsonResponse
    {
        abort_unless((int) $oportunidad->user_id === (int) $request->user()->id, 404);
        $datos = $request->validate([
            'estado' => ['sometimes', 'required', Rule::in(FlujoComercial::ESTADOS)],
            'notas' => ['nullable', 'string', 'max:4000'],
            'proxima_gestion' => ['nullable', 'date_format:Y-m-d'],
            'condiciones_acordadas' => ['nullable', 'string', 'max:4000'],
        ]);

        $oportunidad = DB::transaction(function () use ($oportunidad, $datos) {
            $oportunidad = Oportunidad::query()->lockForUpdate()->findOrFail($oportunidad->id);
            $oportunidad->fill($datos);
            if ($oportunidad->primer_pedido_en === null && in_array($oportunidad->estado, ['primer_pedido', 'punto_venta_activo'], true)) {
                $oportunidad->primer_pedido_en = now();
            }
            $oportunidad->save();
            if ($oportunidad->wasChanged(['estado', 'primer_pedido_en'])) {
                DB::afterCommit(fn () => Cache::forget(CacheComercial::INDICADORES));
            }

            return $oportunidad;
        });

        return response()->json(['oportunidad' => $oportunidad]);
    }

    public function propuestas(Request $request, Oportunidad $oportunidad): JsonResponse
    {
        abort_unless((int) $oportunidad->user_id === (int) $request->user()->id, 404);

        return response()->json(['propuestas' => $oportunidad->propuestas()->latest('id')->get()]);
    }
}
