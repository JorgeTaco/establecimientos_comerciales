<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AdministracionController;
use App\Http\Controllers\Api\FichaEstablecimientoController;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Support\DatosEstablecimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComercioController
{
    public function create(): View
    {
        return view('comercio.editar', $this->categorias() + ['establecimiento' => null, 'edicion' => null]);
    }

    public function edit(Request $request, Establecimiento $establecimiento, FichaEstablecimientoController $api): View
    {
        $datos = $api->privada($request, $establecimiento)->getData(true);

        return view('comercio.editar', $datos + $this->categorias());
    }

    public function store(Request $request, FichaEstablecimientoController $api, DatosEstablecimiento $datos): RedirectResponse
    {
        $this->prepararFormulario($request);
        $establecimiento = $api->store($request, $datos)->getData(true)['establecimiento'];

        return to_route('establecimientos.ficha', $establecimiento['id'])->with('status', 'Establecimiento registrado.');
    }

    public function update(Request $request, Establecimiento $establecimiento, FichaEstablecimientoController $api, DatosEstablecimiento $datos): RedirectResponse
    {
        $this->prepararFormulario($request);
        $api->update($request, $establecimiento, $datos);

        return to_route('establecimientos.ficha', $establecimiento)->with('status', 'Ficha actualizada.');
    }

    public function verificar(Establecimiento $establecimiento, AdministracionController $api): RedirectResponse
    {
        $api->verificar($establecimiento);

        return to_route('establecimientos.ficha', $establecimiento)->with('status', 'Ficha verificada.');
    }

    private function categorias(): array
    {
        return ['categorias' => Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre'])];
    }

    private function prepararFormulario(Request $request): void
    {
        $datos = $request->validate(['perfil' => ['required', 'array'], 'productos_texto' => ['nullable', 'string', 'max:4000']]);
        $productos = str_getcsv($datos['productos_texto'] ?? '', ',', '"', '');
        $perfil = $datos['perfil'];
        $perfil['productos_buscados'] = array_values(array_filter(array_map(fn ($valor) => trim((string) $valor), $productos)));
        $perfil['canales_contacto'] ??= [];
        $request->merge(['perfil' => $perfil, 'categorias' => $request->input('categorias', [])]);
    }
}
