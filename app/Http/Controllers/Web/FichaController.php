<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AdministracionController;
use App\Http\Resources\FichaEstablecimientoResource;
use App\Models\Establecimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FichaController
{
    public function show(Request $request, Establecimiento $establecimiento): View
    {
        $perfil = (new FichaEstablecimientoResource($establecimiento->load('perfilComercial', 'categorias')))
            ->toArray($request);
        [$tono, $etiqueta, $descripcion] = match ($perfil['recepcionProveedores']) {
            'recibe_propuestas' => ['positivo', 'Recibe propuestas', 'Este comercio acepta propuestas de proveedores.'],
            'no_recibe_por_ahora' => ['negativo', 'No recibe por ahora', 'Por ahora no está comprando a proveedores nuevos.'],
            default => ['pendiente', 'Por confirmar', 'Confirma si recibe propuestas antes de visitarlo.'],
        };

        return view('establecimientos.ficha', [
            'establecimiento' => $perfil + [
                'tipoEtiqueta' => Str::headline($perfil['tipo']),
                'modalidadEtiqueta' => $perfil['modalidad_compra'] ? Str::headline($perfil['modalidad_compra']) : null,
                'decisionEtiqueta' => $perfil['decision_compra'] ? Str::headline($perfil['decision_compra']) : null,
            ],
            'estadoTono' => $tono,
            'estadoEtiqueta' => $etiqueta,
            'estadoDescripcion' => $descripcion,
        ]);
    }

    public function reportar(Request $request, Establecimiento $establecimiento, AdministracionController $api): RedirectResponse
    {
        $api->reporte($request, $establecimiento);

        return to_route('establecimientos.ficha', $establecimiento)->with('status', 'Reporte enviado.');
    }
}
