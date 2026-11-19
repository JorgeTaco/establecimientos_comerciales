<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\Oportunidad;
use App\Models\Producto;
use App\Models\Propuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OportunidadController extends Controller
{
    public function create($establecimientoId)
    {
        $establecimiento = Establecimiento::query()->find($establecimientoId);

        if (! $establecimiento) {
            return redirect()->route('establecimientos.index')->withErrors(['error' => 'No se pudo cargar el comercio.']);
        }

        return view('oportunidades.crear', [
            'establecimiento' => [
                'id' => (string) $establecimiento->id,
                'nombre' => $establecimiento->nombre,
                'tipoEtiqueta' => ucfirst($establecimiento->tipo),
                'parroquia' => $establecimiento->parroquia,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $usuario = Auth::user();

        $datos = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
        ]);

        $oportunidad = $usuario->oportunidades()->firstOrCreate($datos);

        return redirect()->route('oportunidades.index')->with('status', 'Oportunidad creada correctamente.');
    }

    public function propuesta(Request $request, $establecimientoId)
    {
        $usuario = Auth::user();

        $datos = $request->validate([
            'productos' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'productos.*' => ['required', 'integer', 'distinct'],
            'mensaje' => ['required', 'string', 'max:4000'],
            'referencia' => ['nullable', 'uuid'],
        ]);

        $establecimiento = Establecimiento::query()->lockForUpdate()->findOrFail($establecimientoId);

        if ($establecimiento->recepcion_proveedores === 'no_recibe_por_ahora') {
            return back()->withErrors(['error' => 'El establecimiento no recibe propuestas por ahora.'])->withInput();
        }

        $oportunidad = $usuario->oportunidades()->firstOrCreate(['establecimiento_id' => $establecimiento->id]);

        if (isset($datos['referencia'])) {
            $anterior = $oportunidad->propuestas()->where('referencia', $datos['referencia'])->first();
            if ($anterior !== null) {
                $ids = array_map('intval', $datos['productos']);
                if ($anterior->mensaje !== $datos['mensaje'] || array_column($anterior->productos, 'id') !== $ids) {
                    return back()->withErrors(['error' => 'La referencia ya pertenece a otra propuesta.'])->withInput();
                }
                return redirect()->route('oportunidades.index')->with('status', 'Propuesta actualizada.');
            }
        }

        $productos = $usuario->productos()->whereNull('archivado_en')->whereKey($datos['productos'])->get()->keyBy('id');
        if ($productos->count() !== count($datos['productos'])) {
            return back()->withErrors(['error' => 'Selecciona productos activos de tu propio catálogo.'])->withInput();
        }

        $oportunidad->propuestas()->create([
            'productos' => array_map(fn ($id) => $productos[$id]->only([
                'id', 'nombre', 'presentacion', 'precio_comercio', 'pedido_minimo',
            ]), $datos['productos']),
            'mensaje' => $datos['mensaje'],
            'referencia' => $datos['referencia'] ?? null,
        ])->refresh();

        return redirect()->route('oportunidades.index')->with('status', 'Propuesta enviada correctamente.');
    }
}
