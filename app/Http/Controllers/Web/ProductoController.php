<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index()
    {
        $usuario = Auth::user();
        $productos = $usuario->productos()->orderBy('nombre')->orderBy('id')->get();
        $categorias = Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('productos.index', [
            'productos' => $productos,
            'categorias' => $categorias,
        ]);
    }

    public function create()
    {
        $categorias = Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('productos.crear', [
            'categorias' => $categorias,
        ]);
    }

    public function store(Request $request)
    {
        $usuario = Auth::user();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', Rule::exists('categorias', 'id')->where('activa', true)],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'foto_url' => ['nullable', 'url:http,https', 'max:2048'],
            'presentacion' => ['nullable', 'string', 'max:255'],
            'precio_comercio' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'precio_sugerido' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'pedido_minimo' => ['nullable', 'integer', 'between:1,1000000000'],
            'capacidad_semanal' => ['nullable', 'integer', 'between:1,1000000000'],
            'vida_util_dias' => ['nullable', 'integer', 'between:1,36500'],
            'requiere_refrigeracion' => ['sometimes', 'required', 'boolean'],
            'conservacion' => ['nullable', 'string', 'max:2000'],
            'zonas_entrega' => ['nullable', 'array', 'list', 'max:50'],
            'zonas_entrega.*' => ['required', 'string', 'max:100', 'distinct'],
            'frecuencia_entrega' => ['nullable', 'string', 'max:255'],
        ]);

        $valores = $datos;
        $minimo = $datos['pedido_minimo'] ?? null;
        $capacidad = $datos['capacidad_semanal'] ?? null;
        if ($minimo !== null && $capacidad !== null && $minimo > $capacidad) {
            throw ValidationException::withMessages(['pedido_minimo' => 'El pedido mínimo supera la capacidad semanal.']);
        }

        $producto = $usuario->productos()->create($valores);

        return redirect()->route('productos.index')->with('status', 'Producto creado correctamente.');
    }

    public function show(Producto $producto)
    {
        abort_unless((int) $producto->user_id === (int) Auth::id(), 403);

        return view('productos.show', [
            'producto' => $producto,
        ]);
    }

    public function edit(Producto $producto)
    {
        abort_unless((int) $producto->user_id === (int) Auth::id(), 403);

        $categorias = Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('productos.editar', [
            'producto' => $producto,
            'categorias' => $categorias,
        ]);
    }

    public function update(Request $request, Producto $producto)
    {
        abort_unless((int) $producto->user_id === (int) Auth::id(), 403);
        abort_if($producto->archivado_en !== null, 409, 'El producto está archivado.');

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'categoria_id' => ['sometimes', 'required', Rule::exists('categorias', 'id')->where('activa', true)],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'foto_url' => ['nullable', 'url:http,https', 'max:2048'],
            'presentacion' => ['nullable', 'string', 'max:255'],
            'precio_comercio' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'precio_sugerido' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'pedido_minimo' => ['nullable', 'integer', 'between:1,1000000000'],
            'capacidad_semanal' => ['nullable', 'integer', 'between:1,1000000000'],
            'vida_util_dias' => ['nullable', 'integer', 'between:1,36500'],
            'requiere_refrigeracion' => ['sometimes', 'required', 'boolean'],
            'conservacion' => ['nullable', 'string', 'max:2000'],
            'zonas_entrega' => ['nullable', 'array', 'list', 'max:50'],
            'zonas_entrega.*' => ['required', 'string', 'max:100', 'distinct'],
            'frecuencia_entrega' => ['nullable', 'string', 'max:255'],
        ]);

        $valores = $datos;
        $minimo = $datos['pedido_minimo'] ?? $producto->pedido_minimo;
        $capacidad = $datos['capacidad_semanal'] ?? $producto->capacidad_semanal;
        if ($minimo !== null && $capacidad !== null && $minimo > $capacidad) {
            throw ValidationException::withMessages(['pedido_minimo' => 'El pedido mínimo supera la capacidad semanal.']);
        }

        $producto->update($valores);

        return redirect()->route('productos.index')->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        abort_unless((int) $producto->user_id === (int) Auth::id(), 403);

        if ($producto->archivado_en === null) {
            $producto->update(['archivado_en' => now()]);
        }

        return redirect()->route('productos.index')->with('status', 'Producto archivado correctamente.');
    }

    public function compatibles(Producto $producto)
    {
        abort_unless((int) $producto->user_id === (int) Auth::id(), 403);
        abort_if($producto->archivado_en !== null, 409, 'El producto está archivado.');

        return view('productos.compatibles', [
            'producto' => $producto,
        ]);
    }
}
