<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EstablecimientoResource;
use App\Models\Establecimiento;
use App\Models\Producto;
use App\Support\FlujoComercial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusquedaEstablecimientoController
{
    public function buscar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'canton' => ['nullable', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'recepcionProveedores' => ['nullable', Rule::in(FlujoComercial::RECEPCION)],
            'latitud' => ['required_with:longitud,distancia_km', 'nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['required_with:latitud,distancia_km', 'nullable', 'numeric', 'between:-180,180'],
            'distancia_km' => ['nullable', 'numeric', 'gt:0', 'max:500'],
        ]);

        $consulta = Establecimiento::query()->orderBy('nombre')->orderBy('id')
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $patron = '%'.$texto.'%';
                $q->where(fn ($query) => $query->whereLike('nombre', $patron)
                    ->orWhereLike('parroquia', $patron)
                    ->orWhereHas('perfilComercial', fn ($p) => $p
                        ->whereLike('direccion', $patron)->orWhereLike('sector', $patron)->orWhereLike('canton', $patron)));
            })
            ->when($filtros['canton'] ?? null, fn ($q, $canton) => $q->whereHas('perfilComercial', fn ($p) => $p->where('canton', $canton)))
            ->when($filtros['sector'] ?? null, fn ($q, $sector) => $q->where(fn ($p) => $p->where('parroquia', $sector)
                ->orWhereHas('perfilComercial', fn ($perfil) => $perfil->where('sector', $sector))))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['recepcionProveedores'] ?? null, fn ($q, $estado) => $q->where('recepcion_proveedores', $estado))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $id) => $q->whereHas('categorias', fn ($c) => $c->whereKey($id)->where('activa', true)));

        $resultados = $consulta->get()->map(function (Establecimiento $comercio) use ($filtros, $request) {
            return (new EstablecimientoResource($comercio))->toArray($request)
                + ['distanciaKm' => $this->distancia($comercio, $filtros)];
        });

        if (isset($filtros['distancia_km'])) {
            $resultados = $resultados->filter(fn (array $fila) => $fila['distanciaKm'] !== null
                && $fila['distanciaKm'] <= $filtros['distancia_km']);
        }
        if (isset($filtros['latitud'], $filtros['longitud'])) {
            $resultados = $resultados->sortBy(fn (array $fila) => $fila['distanciaKm'] ?? INF);
        }

        return response()->json(['establecimientos' => $resultados->values()]);
    }

    public function compatibles(Request $request, Producto $producto): JsonResponse
    {
        abort_unless((int) $producto->user_id === (int) $request->user()->id && $producto->archivado_en === null, 404);

        $zonas = array_map(fn (string $zona) => Str::slug($zona), $producto->zonas_entrega ?? []);
        $comercios = Establecimiento::query()->whereHas('categorias', fn ($q) => $q->whereKey($producto->categoria_id)->where('activa', true))
            ->where('recepcion_proveedores', '!=', 'no_recibe_por_ahora')
            ->with('perfilComercial')->orderBy('nombre')->orderBy('id')->get();
        $resultados = [];

        foreach ($comercios as $comercio) {
            $perfil = $comercio->perfilComercial;
            $ubicacion = array_map(fn (?string $zona) => Str::slug($zona ?? ''), [$comercio->parroquia, $perfil?->sector, $perfil?->canton]);
            $pedido = $perfil?->pedido_inicial;

            if (($zonas !== [] && ! array_intersect($zonas, $ubicacion))
                || ($producto->requiere_refrigeracion && $perfil?->tiene_refrigeracion === false)
                || ($pedido !== null && $producto->capacidad_semanal !== null && $pedido > $producto->capacidad_semanal)
                || ($pedido !== null && $producto->pedido_minimo !== null && $pedido < $producto->pedido_minimo)) {
                continue;
            }

            $motivos = ['Vende productos de tu categoría.'];
            $pendientes = [];
            if ($zonas !== []) {
                $motivos[] = 'Está dentro de tu zona de entrega.';
            } else {
                $pendientes[] = 'Indica tu zona de entrega.';
            }
            if ($comercio->recepcion_proveedores === 'recibe_propuestas') {
                $motivos[] = 'Recibe propuestas de proveedores.';
            } else {
                $pendientes[] = 'Confirma si recibe propuestas.';
            }
            if ($producto->requiere_refrigeracion) {
                if ($perfil?->tiene_refrigeracion === true) {
                    $motivos[] = 'Cuenta con refrigeración.';
                } else {
                    $pendientes[] = 'Confirma las condiciones de refrigeración.';
                }
            }
            if ($pedido !== null && $producto->capacidad_semanal !== null && $producto->pedido_minimo !== null) {
                $motivos[] = 'El pedido inicial está dentro de tu rango de abastecimiento.';
            } else {
                $pendientes[] = 'Confirma el pedido inicial y el rango de abastecimiento.';
            }

            $resultados[] = (new EstablecimientoResource($comercio))->toArray($request) + [
                'motivos' => $motivos,
                'pendientes' => $pendientes,
            ];
        }

        return response()->json([
            'establecimientos' => $resultados,
            'aviso' => 'La compatibilidad no garantiza la aceptación del producto.',
        ]);
    }

    private function distancia(Establecimiento $comercio, array $filtros): ?float
    {
        if (! isset($filtros['latitud'], $filtros['longitud'], $comercio->latitud, $comercio->longitud)) {
            return null;
        }

        $latitud = deg2rad($filtros['latitud']);
        $destino = deg2rad($comercio->latitud);
        $longitud = deg2rad($comercio->longitud - $filtros['longitud']);
        $a = sin(($destino - $latitud) / 2) ** 2 + cos($latitud) * cos($destino) * sin($longitud / 2) ** 2;

        return 6371.0088 * 2 * asin(sqrt(min(1, $a)));
    }
}
