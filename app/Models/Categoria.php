<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'activa'])]
class Categoria extends Model
{
    protected $table = 'categorias';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
