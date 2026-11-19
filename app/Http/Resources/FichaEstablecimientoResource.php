<?php

namespace App\Http\Resources;

use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Establecimiento */
class FichaEstablecimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $perfil = $this->perfilComercial;
        $ubicacion = (new EstablecimientoResource($this->resource))->toArray($request);
        if (config('app.publicar_interes_encuesta')) {
            $ubicacion['interesDeclarado'] = $perfil?->interes_encuesta;
        }
        $destino = $ubicacion['latitud'] === null
            ? $perfil?->direccion
            : $ubicacion['latitud'].','.$ubicacion['longitud'];

        $contacto = null;
        if ($perfil?->contacto_autorizado_en !== null) {
            $canales = $perfil->canales_contacto ?? [];
            $contacto = [
                'nombre' => $perfil->contacto_nombre,
                'telefono' => array_intersect($canales, ['telefono', 'whatsapp']) ? $perfil->contacto_telefono : null,
                'email' => in_array('email', $canales, true) ? $perfil->contacto_email : null,
                'canales' => $canales,
            ];
        }

        return $ubicacion + [
            'foto_url' => $perfil?->foto_url,
            'direccion' => $perfil?->direccion,
            'canton' => $perfil?->canton,
            'sector' => $perfil?->sector,
            'categorias' => $this->categorias->map->only(['id', 'nombre'])->values(),
            'productos_buscados' => $perfil?->productos_buscados ?? [],
            'tiene_refrigeracion' => $perfil?->tiene_refrigeracion,
            'horario_atencion' => $perfil?->horario_atencion,
            'horario_proveedores' => $perfil?->horario_proveedores,
            'requisitos' => $perfil?->requisitos,
            'modalidad_compra' => $perfil?->modalidad_compra,
            'pedido_inicial' => $perfil?->pedido_inicial,
            'devoluciones' => $perfil?->devoluciones,
            'decision_compra' => $perfil?->decision_compra,
            'verificado_en' => $perfil?->verificado_en?->toISOString(),
            'actualizado_en' => ($perfil?->updated_at ?? $this->updated_at)?->toISOString(),
            'contacto' => $contacto,
            'como_llegar_url' => $destino === null ? null : 'https://www.google.com/maps/dir/?'.http_build_query([
                'api' => 1,
                'destination' => $destino,
            ], '', '&', PHP_QUERY_RFC3986),
        ];
    }
}
