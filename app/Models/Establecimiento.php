<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nombre', 'tipo', 'parroquia', 'latitud', 'longitud', 'recepcion_proveedores'])]
class Establecimiento extends Model
{
    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    public function perfilComercial(): HasOne
    {
        return $this->hasOne(PerfilComercial::class);
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'categoria_establecimiento')->orderBy('nombre');
    }
}
