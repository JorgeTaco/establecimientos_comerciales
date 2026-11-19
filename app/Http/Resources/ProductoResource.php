<?php

namespace App\Http\Resources;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Producto */
class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only([
            'id', 'categoria_id', 'nombre', 'descripcion', 'foto_url', 'presentacion',
            'precio_comercio', 'precio_sugerido', 'pedido_minimo', 'capacidad_semanal',
            'vida_util_dias', 'requiere_refrigeracion', 'conservacion', 'zonas_entrega',
            'frecuencia_entrega', 'documentacion', 'archivado_en',
        ]);
    }
}
