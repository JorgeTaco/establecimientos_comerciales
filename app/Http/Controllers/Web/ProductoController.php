<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\CatalogoController as ApiCatalogo;
use App\Http\Controllers\Api\ProductoController as ApiProductos;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductoController
{
    public function index(Request $request): View
    {
        return view('productos.index', [
            'productos' => $request->user()->productos()->with('categoria')->orderBy('nombre')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('productos.crear', ['categorias' => $this->categorias()]);
    }

    public function show(Request $request, Producto $producto): View
    {
        abort_unless((int) $request->user()->id === (int) $producto->user_id, 404);

        return view('productos.show', ['producto' => $producto->load('categoria')]);
    }

    public function edit(Request $request, Producto $producto): View
    {
        abort_unless((int) $request->user()->id === (int) $producto->user_id, 404);
        abort_if($producto->archivado_en !== null, 409, 'El producto está archivado.');

        return view('productos.editar', ['producto' => $producto, 'categorias' => $this->categorias()]);
    }

    public function destroy(Request $request, Producto $producto, ApiProductos $api): RedirectResponse
    {
        $api->destroy($request, $producto);

        return to_route('productos.index')->with('status', 'Producto archivado.');
    }

    public function compatibles(Request $request, Producto $producto): View
    {
        abort_unless((int) $request->user()->id === (int) $producto->user_id && $producto->archivado_en === null, 404);

        return view('productos.compatibles', ['producto' => $producto]);
    }

    public function compartir(Request $request, ApiCatalogo $api): RedirectResponse
    {
        $datos = $api->compartir($request)->getData(true);

        return to_route('productos.index')->with('catalogo_url', $datos['url_web']);
    }

    public function revocar(Request $request, ApiCatalogo $api): RedirectResponse
    {
        $api->revocar($request);

        return to_route('productos.index')->with('status', 'Enlace del catálogo revocado.');
    }

    public function catalogo(string $token, ApiCatalogo $api): Response
    {
        $datos = $api->publico($token)->getData(true);

        return response()->view('productos.catalogo', $datos)
            ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    private function categorias()
    {
        return Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']);
    }
}
