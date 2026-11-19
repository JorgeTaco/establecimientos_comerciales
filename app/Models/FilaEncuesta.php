<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['establecimiento_id', 'numero', 'nombre', 'estado', 'celdas', 'revision'])]
#[Hidden(['celdas', 'revision'])]
class FilaEncuesta extends Model
{
    public const ESTADOS = [
        'importada' => 'Importada',
        'posible_duplicado' => 'Posible duplicado',
        'revisar' => 'Ubicación por revisar',
        'invalida' => 'Fila inválida',
    ];

    protected $table = 'filas_encuesta';

    protected function casts(): array
    {
        return ['celdas' => 'encrypted:array', 'revision' => 'encrypted:array'];
    }
}
