<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FichaEstablecimientoResource;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Oportunidad;
use App\Models\ReporteEstablecimiento;
use App\Models\User;
use App\Support\DatosEstablecimiento;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdministracionController
{
    public function categorias(): JsonResponse
    {
        return response()->json(['categorias' => Categoria::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function todasCategorias(): JsonResponse
    {
        return response()->json(['categorias' => Categoria::query()->orderBy('nombre')->get(['id', 'nombre', 'activa'])]);
    }

    public function guardarCategoria(Request $request, ?Categoria $categoria = null): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => [$categoria?->exists ? 'sometimes' : 'required', 'required', 'string', 'max:255', Rule::unique('categorias', 'nombre')->ignore($categoria)],
            'activa' => ['sometimes', 'required', 'boolean'],
        ]);
        $categoria ??= new Categoria;
        $categoria->fill($datos)->save();

        return response()->json(['categoria' => $categoria->refresh()->only(['id', 'nombre', 'activa'])], $categoria->wasRecentlyCreated ? 201 : 200);
    }

    public function reporte(Request $request, Establecimiento $establecimiento): JsonResponse
    {
        $datos = $request->validate(['descripcion' => ['required', 'string', 'max:4000']]);
        $reporte = ReporteEstablecimiento::query()->create($datos + [
            'establecimiento_id' => $establecimiento->id,
            'user_id' => $request->user()->id,
        ])->refresh();

        return response()->json(['reporte' => $reporte->only(['id', 'establecimiento_id', 'descripcion', 'estado', 'created_at'])], 201);
    }

    public function reportes(): JsonResponse
    {
        return response()->json(['reportes' => ReporteEstablecimiento::query()->latest('id')->paginate(50)]);
    }

    public function resolver(Request $request, ReporteEstablecimiento $reporte): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['resuelto', 'descartado'])],
            'resolucion' => ['required', 'string', 'max:4000'],
        ]);
        $reporte->update($datos);

        return response()->json(['reporte' => $reporte]);
    }

    public function verificar(Establecimiento $establecimiento): JsonResponse
    {
        $establecimiento = DB::transaction(function () use ($establecimiento) {
            $establecimiento = Establecimiento::query()->lockForUpdate()->findOrFail($establecimiento->id);
            $establecimiento->perfilComercial()->updateOrCreate([], ['verificado_en' => now()]);

            return $establecimiento->load('perfilComercial', 'categorias');
        });

        return response()->json(['establecimiento' => new FichaEstablecimientoResource($establecimiento)]);
    }

    public function asignar(Request $request, Establecimiento $establecimiento): JsonResponse
    {
        $datos = $request->validate([
            'responsable_id' => ['present', 'nullable', Rule::exists('users', 'id')->where('rol', 'comercio')->whereNull('suspendido_en')],
        ]);
        $perfil = DB::transaction(function () use ($datos, $establecimiento) {
            $establecimiento = Establecimiento::query()->lockForUpdate()->findOrFail($establecimiento->id);
            $perfil = $establecimiento->perfilComercial()->firstOrNew();
            if ((int) $perfil->user_id !== (int) $datos['responsable_id']) {
                $perfil->user_id = $datos['responsable_id'];
                $perfil->verificado_en = null;
                $perfil->contacto_autorizado_en = null;
            }
            $establecimiento->perfilComercial()->save($perfil);

            return $perfil;
        });

        return response()->json(['responsable_id' => $perfil->user_id]);
    }

    public function usuarios(): JsonResponse
    {
        return response()->json(['usuarios' => User::query()->orderBy('id')->paginate(50, ['id', 'name', 'email', 'rol', 'suspendido_en'])]);
    }

    public function actualizarUsuario(Request $request, User $usuario): JsonResponse
    {
        abort_if($usuario->rol === 'administrador', 403);
        $datos = $request->validate([
            'rol' => ['sometimes', 'required', Rule::in(['emprendedor', 'comercio'])],
            'suspendido' => ['sometimes', 'required', 'boolean'],
        ]);
        DB::transaction(function () use ($usuario, $datos) {
            $usuario = User::query()->lockForUpdate()->findOrFail($usuario->id);
            abort_if($usuario->rol === 'administrador', 403);
            if (isset($datos['rol'])) {
                $usuario->rol = $datos['rol'];
            }
            if (array_key_exists('suspendido', $datos)) {
                $usuario->suspendido_en = $datos['suspendido'] ? ($usuario->suspendido_en ?? now()) : null;
            }
            $usuario->save();
        });

        return response()->json(['usuario' => $usuario->refresh()->only(['id', 'name', 'email', 'rol', 'suspendido_en'])]);
    }

    public function indicadores(): JsonResponse
    {
        return response()->json(['indicadores' => [
            'emprendedores_con_primer_pedido' => Oportunidad::query()->whereNotNull('primer_pedido_en')->distinct()->count('user_id'),
            'puntos_venta_activos' => Oportunidad::query()->where('estado', 'punto_venta_activo')->count(),
        ]]);
    }

    public function importacion(Request $request, DatosEstablecimiento $normalizador): JsonResponse
    {
        $datos = $request->validate([
            'filas' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'filas.*' => ['array'],
        ]);

        $resultados = [];
        foreach ($datos['filas'] as $indice => $fila) {
            try {
                $validado = Validator::make($fila, $normalizador->reglas(true, importacion: true))->validate();
                [$establecimiento, $duplicados] = $normalizador->importar($validado);
                $resultados[] = $establecimiento === null
                    ? ['fila' => $indice, 'estado' => 'posible_duplicado', 'candidatos' => $duplicados]
                    : ['fila' => $indice, 'estado' => 'creado', 'establecimiento_id' => (string) $establecimiento->id];
            } catch (ValidationException $error) {
                $resultados[] = ['fila' => $indice, 'estado' => 'invalida', 'errores' => $error->errors()];
            } catch (LockTimeoutException) {
                $resultados[] = ['fila' => $indice, 'estado' => 'reintentar'];
            }
        }

        return response()->json(['resultados' => $resultados]);
    }
}
