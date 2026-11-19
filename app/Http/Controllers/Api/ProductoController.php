<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'productos' => ProductoResource::collection($request->user()->productos()->orderBy('nombre')->orderBy('id')->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $producto = $request->user()->productos()->create($this->validar($request));

        return response()->json(['producto' => new ProductoResource($producto->refresh())], 201);
    }

    public function show(Request $request, Producto $producto): JsonResponse
    {
        $this->autorizar($request, $producto);

        return response()->json(['producto' => new ProductoResource($producto)]);
    }

    public function update(Request $request, Producto $producto): JsonResponse
    {
        $this->autorizar($request, $producto);
        abort_if($producto->archivado_en !== null, 409, 'El producto está archivado.');
        $producto->update($this->validar($request, $producto));

        return response()->json(['producto' => new ProductoResource($producto)]);
    }

    public function destroy(Request $request, Producto $producto): Response
    {
        $this->autorizar($request, $producto);
        if ($producto->archivado_en === null) {
            $producto->update(['archivado_en' => now()]);
        }

        return response()->noContent();
    }

    private function autorizar(Request $request, Producto $producto): void
    {
        abort_unless((int) $producto->user_id === (int) $request->user()->id, 404);
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $requerido = $producto === null ? 'required' : 'sometimes';
        $datos = $request->validate([
            'nombre' => [$requerido, 'required', 'string', 'max:255'],
            'categoria_id' => [$requerido, 'required', Rule::exists('categorias', 'id')->where('activa', true)],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'foto_url' => ['nullable', 'url:http,https', 'max:2048'],
            'presentacion' => ['nullable', 'string', 'max:255'],
            'precio_comercio' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,9999999999.99'],
            'precio_sugerido' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,9999999999.99'],
            'pedido_minimo' => ['nullable', 'integer', 'between:1,1000000000'],
            'capacidad_semanal' => ['nullable', 'integer', 'between:1,1000000000'],
            'vida_util_dias' => ['nullable', 'integer', 'between:1,36500'],
            'requiere_refrigeracion' => ['sometimes', 'required', 'boolean'],
            'conservacion' => ['nullable', 'string', 'max:2000'],
            'zonas_entrega' => ['nullable', 'array', 'list', 'max:50'],
            'zonas_entrega.*' => ['required', 'string', 'max:100', 'distinct'],
            'frecuencia_entrega' => ['nullable', 'string', 'max:255'],
            'documentacion' => ['nullable', 'array', 'list', 'max:30'],
            'documentacion.*' => ['required', 'string', 'max:255'],
        ]);

        $valores = $datos + ($producto?->only(['pedido_minimo', 'capacidad_semanal']) ?? []);
        $minimo = $valores['pedido_minimo'] ?? null;
        $capacidad = $valores['capacidad_semanal'] ?? null;
        if ($minimo !== null && $capacidad !== null && $minimo > $capacidad) {
            throw ValidationException::withMessages(['pedido_minimo' => 'El pedido mínimo supera la capacidad semanal.']);
        }

        return $datos;
    }
}
