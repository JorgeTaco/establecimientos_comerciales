<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FichaEstablecimientoResource;
use App\Models\Establecimiento;
use App\Support\DatosEstablecimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FichaEstablecimientoController
{
    public function show(Establecimiento $establecimiento): JsonResponse
    {
        return response()->json([
            'establecimiento' => new FichaEstablecimientoResource($establecimiento->load('perfilComercial', 'categorias')),
        ]);
    }

    public function propios(Request $request): JsonResponse
    {
        $establecimientos = Establecimiento::query()->whereHas('perfilComercial', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('perfilComercial', 'categorias')->orderBy('nombre')->get();

        return response()->json(['establecimientos' => FichaEstablecimientoResource::collection($establecimientos)]);
    }

    public function store(Request $request, DatosEstablecimiento $datos): JsonResponse
    {
        $campos = $request->validate($datos->reglas(true));
        $responsable = $request->user()->rol === 'comercio' ? $request->user()->id : null;
        $establecimiento = $datos->guardar($campos, responsableId: $responsable);

        return response()->json(['establecimiento' => new FichaEstablecimientoResource($establecimiento)], 201);
    }

    public function duplicados(Request $request, DatosEstablecimiento $datos): JsonResponse
    {
        $campos = $request->validate($datos->reglas(true));

        return response()->json(['candidatos' => $datos->duplicados($campos)]);
    }

    public function privada(Request $request, Establecimiento $establecimiento): JsonResponse
    {
        $this->autorizar($request, $establecimiento);
        $perfil = $establecimiento->perfilComercial;

        return response()->json([
            'establecimiento' => new FichaEstablecimientoResource($establecimiento->load('categorias')),
            'edicion' => $perfil?->only(['user_id', 'contacto_nombre', 'contacto_telefono',
                'contacto_email', 'canales_contacto', 'contacto_autorizado_en']),
        ]);
    }

    public function update(Request $request, Establecimiento $establecimiento, DatosEstablecimiento $datos): JsonResponse
    {
        $this->autorizar($request, $establecimiento);
        $propietario = $request->user()->rol === 'comercio' ? $request->user()->id : null;
        $establecimiento = $datos->guardar($request->validate($datos->reglas(false)), $establecimiento, propietario: $propietario);

        return $this->show($establecimiento);
    }

    private function autorizar(Request $request, Establecimiento $establecimiento): void
    {
        $usuario = $request->user();
        abort_unless($usuario->rol === 'administrador' || (int) $establecimiento->perfilComercial?->user_id === (int) $usuario->id, 404);
    }
}
