<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['establecimiento_id', 'user_id', 'descripcion', 'estado', 'resolucion'])]
class ReporteEstablecimiento extends Model
{
    protected $table = 'reportes_establecimientos';
}
