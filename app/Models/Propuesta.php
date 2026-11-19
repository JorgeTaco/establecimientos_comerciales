<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['productos', 'mensaje', 'referencia', 'respuesta', 'mensaje_respuesta', 'respondida_en'])]
class Propuesta extends Model
{
    protected $table = 'propuestas';

    protected function casts(): array
    {
        return ['productos' => 'array', 'respondida_en' => 'datetime'];
    }

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class);
    }
}
