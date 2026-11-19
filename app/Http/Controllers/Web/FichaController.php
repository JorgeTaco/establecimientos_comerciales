<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;

class FichaController extends Controller
{
    public function show($id)
    {
        $establecimiento = Establecimiento::query()
            ->with('perfilComercial', 'categorias')
            ->find($id);

        if (! $establecimiento) {
            abort(404, 'El comercio no existe.');
        }

        $perfil = $establecimiento->perfilComercial;

        $estados = [
            'recibe_propuestas' => [
                'tono' => 'positivo',
                'etiqueta' => 'Recibe propuestas',
                'descripcion' => 'Este comercio acepta propuestas de proveedores.',
            ],
            'no_recibe_por_ahora' => [
                'tono' => 'negativo',
                'etiqueta' => 'No recibe por ahora',
                'descripcion' => 'Por ahora no está comprando a proveedores nuevos.',
            ],
            'por_confirmar' => [
                'tono' => 'pendiente',
                'etiqueta' => 'Por confirmar',
                'descripcion' => 'Aún no hemos verificado si compra a proveedores. Confírmalo antes de visitarlo.',
            ],
        ];

        $estadoClave = $establecimiento->recepcion_proveedores ?? 'por_confirmar';
        $estado = $estados[$estadoClave] ?? $estados['por_confirmar'];

        $categorias = $establecimiento->categorias->map->only(['id', 'nombre'])->values()->all();
        $productosBuscados = $perfil?->productos_buscados ?? [];

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

        $destino = $establecimiento->latitud !== null
            ? $establecimiento->latitud.','.$establecimiento->longitud
            : ($perfil?->direccion ?? null);

        $comoLlegarUrl = $destino !== null
            ? 'https://www.google.com/maps/dir/?'.http_build_query(['api' => 1, 'destination' => $destino], '', '&', PHP_QUERY_RFC3986)
            : null;

        $data = [
            'id' => (string) $establecimiento->id,
            'nombre' => $establecimiento->nombre,
            'tipo' => $establecimiento->tipo,
            'tipoEtiqueta' => ucfirst($establecimiento->tipo),
            'parroquia' => $establecimiento->parroquia,
            'latitud' => $establecimiento->latitud,
            'longitud' => $establecimiento->longitud,
            'recepcionProveedores' => $establecimiento->recepcion_proveedores,
            'direccion' => $perfil?->direccion,
            'canton' => $perfil?->canton,
            'sector' => $perfil?->sector,
            'categorias' => $categorias,
            'productos_buscados' => $productosBuscados,
            'tiene_refrigeracion' => $perfil?->tiene_refrigeracion,
            'horario_atencion' => $perfil?->horario_atencion,
            'horario_proveedores' => $perfil?->horario_proveedores,
            'requisitos' => $perfil?->requisitos,
            'modalidad_compra' => $perfil?->modalidad_compra,
            'modalidadEtiqueta' => $perfil?->modalidad_compra ? ucfirst($perfil->modalidad_compra) : null,
            'pedido_inicial' => $perfil?->pedido_inicial,
            'devoluciones' => $perfil?->devoluciones,
            'decision_compra' => $perfil?->decision_compra,
            'decisionEtiqueta' => $perfil?->decision_compra ? ucfirst($perfil->decision_compra) : null,
            'verificado_en' => $perfil?->verificado_en?->format('Y-m-d'),
            'actualizado_en' => ($perfil?->updated_at ?? $establecimiento->updated_at)?->toISOString(),
            'contacto' => $contacto,
            'como_llegar_url' => $comoLlegarUrl,
            'foto_url' => $perfil?->foto_url,
        ];

        return view('establecimientos.ficha', [
            'establecimiento' => $data,
            'estadoTono' => $estado['tono'],
            'estadoEtiqueta' => $estado['etiqueta'],
            'estadoDescripcion' => $estado['descripcion'],
        ]);
    }
}
