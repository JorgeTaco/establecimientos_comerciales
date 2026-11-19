<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'foto_url', 'direccion', 'canton', 'sector', 'productos_buscados', 'tiene_refrigeracion',
    'contacto_nombre', 'contacto_telefono', 'contacto_email', 'canales_contacto',
    'contacto_autorizado_en', 'horario_proveedores', 'requisitos', 'modalidad_compra',
    'pedido_inicial', 'devoluciones', 'decision_compra', 'verificado_en',
])]
class PerfilComercial extends Model
{
    protected $table = 'perfiles_comerciales';

    protected function casts(): array
    {
        return [
            'productos_buscados' => 'array',
            'canales_contacto' => 'array',
            'tiene_refrigeracion' => 'boolean',
            'interes_encuesta' => 'boolean',
            'contacto_autorizado_en' => 'datetime',
            'verificado_en' => 'datetime',
        ];
    }
}
