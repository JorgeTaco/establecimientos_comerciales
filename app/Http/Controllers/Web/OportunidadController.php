<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\OportunidadController as ApiOportunidades;
use App\Http\Controllers\Api\PropuestaController as ApiPropuestas;
use App\Models\Establecimiento;
use App\Models\Oportunidad;
use App\Models\Propuesta;
use App\Support\FlujoComercial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OportunidadController
{
    public function create(Establecimiento $establecimiento): View
    {
        return view('oportunidades.crear', [
            'establecimiento' => $establecimiento->only(['id', 'nombre', 'parroquia']) + [
                'tipoEtiqueta' => Str::headline($establecimiento->tipo),
            ],
        ]);
    }

    public function store(Request $request, ApiOportunidades $api): RedirectResponse
    {
        $resultado = $api->store($request)->getData(true);

        return to_route('oportunidades.show', $resultado['oportunidad']['id'])->with('status', 'Oportunidad guardada.');
    }

    public function show(Request $request, Oportunidad $oportunidad, ApiOportunidades $api): View
    {
        $datos = $api->show($request, $oportunidad)->getData(true);

        return view('oportunidades.show', ['oportunidad' => $datos['oportunidad'], 'estados' => FlujoComercial::ESTADOS]);
    }

    public function update(Request $request, Oportunidad $oportunidad, ApiOportunidades $api): RedirectResponse
    {
        $api->update($request, $oportunidad);

        return to_route('oportunidades.show', $oportunidad)->with('status', 'Seguimiento actualizado.');
    }

    public function responder(Request $request, Propuesta $propuesta, ApiPropuestas $api): View
    {
        $api->recibida($request, $propuesta);

        return view('propuestas.responder', ['propuestaId' => $propuesta->id]);
    }
}
