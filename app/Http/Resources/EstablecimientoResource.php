<?php

namespace App\Http\Resources;

use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Establecimiento */
class EstablecimientoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tieneUbicacion = $this->latitud !== null && $this->longitud !== null;

        return [
            'id' => (string) $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'parroquia' => $this->parroquia,
            'latitud' => $tieneUbicacion ? $this->latitud : null,
            'longitud' => $tieneUbicacion ? $this->longitud : null,
            'recepcionProveedores' => $this->recepcion_proveedores,
        ];
    }
}
