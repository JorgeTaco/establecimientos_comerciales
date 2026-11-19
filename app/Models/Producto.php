<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'categoria_id', 'nombre', 'descripcion', 'foto_url', 'presentacion', 'precio_comercio',
    'precio_sugerido', 'pedido_minimo', 'capacidad_semanal', 'vida_util_dias',
    'requiere_refrigeracion', 'conservacion', 'zonas_entrega', 'frecuencia_entrega',
    'documentacion', 'archivado_en',
])]
#[Hidden(['user_id'])]
class Producto extends Model
{
    protected $table = 'productos';

    protected function casts(): array
    {
        return [
            'precio_comercio' => 'decimal:2',
            'precio_sugerido' => 'decimal:2',
            'requiere_refrigeracion' => 'boolean',
            'zonas_entrega' => 'array',
            'documentacion' => 'array',
            'archivado_en' => 'datetime',
        ];
    }
}
