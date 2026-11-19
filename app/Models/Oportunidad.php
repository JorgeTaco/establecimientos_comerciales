<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['establecimiento_id', 'estado', 'notas', 'proxima_gestion', 'condiciones_acordadas'])]
#[Hidden(['user_id'])]
class Oportunidad extends Model
{
    protected $table = 'oportunidades';

    protected function casts(): array
    {
        return ['proxima_gestion' => 'date:Y-m-d', 'primer_pedido_en' => 'datetime'];
    }

    public function propuestas(): HasMany
    {
        return $this->hasMany(Propuesta::class);
    }
}
