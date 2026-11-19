<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['huella', 'archivo', 'fila_encabezado', 'encabezados', 'resumen'])]
class EncuestaImportada extends Model
{
    protected $table = 'encuestas_importadas';

    protected function casts(): array
    {
        return ['encabezados' => 'array', 'resumen' => 'array'];
    }

    public function filas(): HasMany
    {
        return $this->hasMany(FilaEncuesta::class, 'encuesta_id');
    }
}
