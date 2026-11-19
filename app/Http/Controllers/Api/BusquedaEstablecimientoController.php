<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EstablecimientoResource;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Producto;
use App\Support\CacheComercial;
use App\Support\CursorPaginacion;
use App\Support\FlujoComercial;
use App\Support\RadioGeografico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusquedaEstablecimientoController
{
    private const COLUMNAS = ['id', 'nombre', 'tipo', 'parroquia', 'latitud', 'longitud', 'recepcion_proveedores'];

    public function buscar(Request $request): JsonResponse
    {
        $publicarInteres = (bool) config('app.publicar_interes_encuesta');
        $filtros = $request->validate([
            'facetas' => ['sometimes', 'boolean'],
            'limite' => ['sometimes', 'integer', 'between:1,50'],
            'modo' => ['sometimes', Rule::in(['mapa'])],
            'ubicados' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'canton' => ['nullable', 'string', 'max:100'],
            'parroquia' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'recepcionProveedores' => ['nullable', Rule::in(FlujoComercial::RECEPCION)],
            'interes_declarado' => [Rule::prohibitedIf(! $publicarInteres), 'nullable', 'boolean'],
            'latitud' => ['required_with:longitud,distancia_km', 'nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['required_with:latitud,distancia_km', 'nullable', 'numeric', 'between:-180,180'],
            'distancia_km' => ['nullable', 'numeric', 'gt:0', 'max:500'],
        ]);

        $cacheable = ! empty($filtros['facetas']) && (int) ($filtros['limite'] ?? 0) === 24
            && $request->query('cursor') === null && Arr::except($filtros, ['limite', 'facetas']) === [];
        $calcular = fn () => $this->consultar($request, $filtros, $publicarInteres);

        return response()->json($cacheable
            ? Cache::remember(CacheComercial::clavePortada($publicarInteres), 60, $calcular)
            : $calcular());
    }

    private function consultar(Request $request, array $filtros, bool $publicarInteres): array
    {
        $base = $this->consultaBase($filtros);
        $consulta = $this->filtrarOpciones(clone $base, $filtros);
        $mapa = ($filtros['modo'] ?? null) === 'mapa';
        $conOrigen = isset($filtros['latitud'], $filtros['longitud']);
        $paginada = isset($filtros['limite']) && ! $mapa && ! $conOrigen;
        $respuesta = [];

        if ($paginada) {
            $pagina = (clone $consulta)->with('perfilComercial')->orderBy('nombre')->orderBy('id')
                ->cursorPaginate((int) $filtros['limite'], self::COLUMNAS, 'cursor', CursorPaginacion::leer($request, porNombre: true));
            $comercios = $pagina->getCollection();
            $respuesta['siguienteCursor'] = $pagina->nextCursor()?->encode();
        } else {
            $filas = (clone $consulta)->orderBy('nombre')->orderBy('id');
            if ($mapa) {
                $filas->whereNotNull('latitud')->whereNotNull('longitud');
            } else {
                $filas->with('perfilComercial');
            }
            $comercios = $this->dentroDelRadio($filas->get(self::COLUMNAS), $filtros);
        }

        $resultados = $comercios->map(fn (Establecimiento $comercio) => $this->fila($request, $comercio, $filtros, $publicarInteres, $mapa));
        if ($conOrigen && ! $mapa) {
            $resultados = $resultados->sortBy(fn (array $fila) => $fila['distanciaKm'] ?? INF);
        }
        $respuesta['establecimientos'] = $resultados->values()->all();

        if (! empty($filtros['facetas'])) {
            $coincidencias = $paginada || $mapa
                ? $this->dentroDelRadio($consulta->get(self::COLUMNAS), $filtros)
                : $comercios;
            $respuesta['facetas'] = $this->facetas($base, $filtros, $coincidencias, $publicarInteres);
            $respuesta['totalDirectorio'] = Establecimiento::query()->count();
            $respuesta['totalCoincidencias'] = $coincidencias->count();
            $respuesta['totalReciben'] = $coincidencias->where('recepcion_proveedores', 'recibe_propuestas')->count();
        }

        return $respuesta;
    }

    private function consultaBase(array $filtros): Builder
    {
        $base = Establecimiento::query()
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $patron = '%'.$texto.'%';
                $q->where(fn ($query) => $query->whereLike('nombre', $patron)
                    ->orWhereLike('parroquia', $patron)
                    ->orWhereHas('perfilComercial', fn ($p) => $p
                        ->whereLike('direccion', $patron)->orWhereLike('sector', $patron)->orWhereLike('canton', $patron)));
            })
            ->when($filtros['canton'] ?? null, fn ($q, $canton) => $q->whereHas('perfilComercial', fn ($p) => $p->where('canton', $canton)))
            ->when($filtros['sector'] ?? null, fn ($q, $sector) => $q->where(fn ($p) => $p->where('parroquia', $sector)
                ->orWhereHas('perfilComercial', fn ($perfil) => $perfil->where('sector', $sector))))
            ->when(! empty($filtros['ubicados']) || isset($filtros['distancia_km']),
                fn ($q) => $q->whereNotNull('latitud')->whereNotNull('longitud'));
        if (isset($filtros['distancia_km'])) {
            RadioGeografico::limitarCandidatos($base, $filtros['latitud'], $filtros['longitud'], $filtros['distancia_km']);
        }

        return $base;
    }

    private function fila(Request $request, Establecimiento $comercio, array $filtros, bool $publicarInteres, bool $mapa): array
    {
        $fila = (new EstablecimientoResource($comercio))->toArray($request);
        if ($mapa) {
            return $fila;
        }

        $perfil = $comercio->perfilComercial;
        $fila += [
            'direccion' => $perfil?->direccion,
            'sector' => $perfil?->sector,
            'foto_url' => $perfil?->foto_url,
            'distanciaKm' => $this->distancia($comercio, $filtros),
        ];
        if ($publicarInteres) {
            $fila['interesDeclarado'] = $perfil?->interes_encuesta;
        }

        return $fila;
    }

    private function filtrarOpciones(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['parroquia'] ?? null, fn ($q, $parroquia) => $q->where('parroquia', $parroquia))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $id) => $q->whereHas('categorias', fn ($c) => $c->whereKey($id)->where('activa', true)))
            ->when($filtros['recepcionProveedores'] ?? null, fn ($q, $estado) => $q->where('recepcion_proveedores', $estado))
            ->when(isset($filtros['interes_declarado']),
                fn ($q) => $q->whereHas('perfilComercial', fn ($p) => $p->where('interes_encuesta', (bool) $filtros['interes_declarado'])));
    }

    private function dentroDelRadio(Collection $comercios, array $filtros): Collection
    {
        if (! isset($filtros['distancia_km'])) {
            return $comercios;
        }

        return $comercios->filter(function (Establecimiento $comercio) use ($filtros) {
            $distancia = $this->distancia($comercio, $filtros);

            return $distancia !== null && $distancia <= $filtros['distancia_km'];
        });
    }

    private function facetas(Builder $base, array $filtros, Collection $coincidencias, bool $publicarInteres): array
    {
        $campos = [
            'tipo' => 'tipo',
            'parroquia' => 'parroquia',
            'recepcionProveedores' => 'recepcion_proveedores',
            'categoria_id' => null,
        ];
        if ($publicarInteres) {
            $campos['interes_declarado'] = 'perfilComercial.interes_encuesta';
        }

        $facetas = [];
        foreach ($campos as $clave => $campo) {
            // Con un filtro activo, las alternativas de esa faceta se cuentan sin ese filtro.
            $candidatos = isset($filtros[$clave])
                ? $this->dentroDelRadio($this->filtrarOpciones(clone $base, Arr::except($filtros, [$clave]))->orderBy('nombre')->orderBy('id')->get(self::COLUMNAS), $filtros)
                : $coincidencias;

            if ($clave === 'categoria_id') {
                $facetas[$clave] = $this->contarCategorias($candidatos, $filtros[$clave] ?? null);

                continue;
            }
            if (in_array($clave, ['tipo', 'parroquia'], true)) {
                // MySQL agrupa estas opciones con la misma intercalación que usa para filtrarlas.
                $facetas[$clave] = Establecimiento::query()->whereKey($candidatos->modelKeys())
                    ->where($campo, '<>', '')
                    ->select($campo)->selectRaw('count(*) as cantidad')->groupBy($campo)->get()
                    ->map(fn (Establecimiento $grupo) => ['valor' => $grupo->{$campo}, 'cantidad' => (int) $grupo->cantidad])
                    ->values()->all();

                continue;
            }
            if ($clave === 'interes_declarado') {
                $candidatos->loadMissing('perfilComercial');
            }

            $facetas[$clave] = $candidatos->pluck($campo)
                ->filter(fn ($valor) => $valor !== null && $valor !== '')
                ->countBy(fn ($valor) => is_bool($valor) ? (int) $valor : $valor)
                ->map(fn (int $cantidad, string|int $valor) => ['valor' => (string) $valor, 'cantidad' => $cantidad])
                ->values()->all();
        }

        return $facetas;
    }

    private function contarCategorias(Collection $comercios, int|string|null $seleccion): array
    {
        $comercios->loadMissing(['categorias' => fn ($q) => $q->where('activa', true)]);
        $opciones = $comercios->flatMap(fn (Establecimiento $comercio) => $comercio->categorias)
            ->groupBy('id')
            ->map(fn ($categorias, $id) => [
                'valor' => (string) $id,
                'nombre' => $categorias->first()->nombre,
                'cantidad' => $categorias->count(),
            ]);
        if ($seleccion !== null && ! $opciones->has((int) $seleccion)) {
            $categoria = Categoria::query()->where('activa', true)->find($seleccion);
            if ($categoria) {
                $opciones->push(['valor' => (string) $categoria->id, 'nombre' => $categoria->nombre, 'cantidad' => 0]);
            }
        }

        return $opciones->values()->all();
    }

    public function compatibles(Request $request, Producto $producto): JsonResponse
    {
        abort_unless((int) $producto->user_id === (int) $request->user()->id && $producto->archivado_en === null, 404);

        $zonas = array_map(fn (string $zona) => Str::slug($zona), $producto->zonas_entrega ?? []);
        $comercios = Establecimiento::query()->whereHas('categorias', fn ($q) => $q->whereKey($producto->categoria_id)->where('activa', true))
            ->where('recepcion_proveedores', '!=', 'no_recibe_por_ahora')
            ->with('perfilComercial')->orderBy('nombre')->orderBy('id')->get();
        $resultados = [];

        foreach ($comercios as $comercio) {
            $perfil = $comercio->perfilComercial;
            $ubicacion = array_map(fn (?string $zona) => Str::slug($zona ?? ''), [$comercio->parroquia, $perfil?->sector, $perfil?->canton]);
            $pedido = $perfil?->pedido_inicial;

            if (($zonas !== [] && ! array_intersect($zonas, $ubicacion))
                || ($producto->requiere_refrigeracion && $perfil?->tiene_refrigeracion === false)
                || ($pedido !== null && $producto->capacidad_semanal !== null && $pedido > $producto->capacidad_semanal)
                || ($pedido !== null && $producto->pedido_minimo !== null && $pedido < $producto->pedido_minimo)) {
                continue;
            }

            $motivos = ['Vende productos de tu categoría.'];
            $pendientes = [];
            if ($zonas !== []) {
                $motivos[] = 'Está dentro de tu zona de entrega.';
            } else {
                $pendientes[] = 'Indica tu zona de entrega.';
            }
            if ($comercio->recepcion_proveedores === 'recibe_propuestas') {
                $motivos[] = 'Recibe propuestas de proveedores.';
            } else {
                $pendientes[] = 'Confirma si recibe propuestas.';
            }
            if ($producto->requiere_refrigeracion) {
                if ($perfil?->tiene_refrigeracion === true) {
                    $motivos[] = 'Cuenta con refrigeración.';
                } else {
                    $pendientes[] = 'Confirma las condiciones de refrigeración.';
                }
            }
            if ($pedido !== null && $producto->capacidad_semanal !== null && $producto->pedido_minimo !== null) {
                $motivos[] = 'El pedido inicial está dentro de tu rango de abastecimiento.';
            } else {
                $pendientes[] = 'Confirma el pedido inicial y el rango de abastecimiento.';
            }

            $resultados[] = (new EstablecimientoResource($comercio))->toArray($request) + [
                'motivos' => $motivos,
                'pendientes' => $pendientes,
            ];
        }

        return response()->json([
            'establecimientos' => $resultados,
            'aviso' => 'La compatibilidad no garantiza la aceptación del producto.',
        ]);
    }

    private function distancia(Establecimiento $comercio, array $filtros): ?float
    {
        if (! isset($filtros['latitud'], $filtros['longitud'], $comercio->latitud, $comercio->longitud)) {
            return null;
        }

        $latitud = deg2rad($filtros['latitud']);
        $destino = deg2rad($comercio->latitud);
        $longitud = deg2rad($comercio->longitud - $filtros['longitud']);
        $a = sin(($destino - $latitud) / 2) ** 2 + cos($latitud) * cos($destino) * sin($longitud / 2) ** 2;

        return RadioGeografico::RADIO_TERRESTRE_KM * 2 * asin(sqrt(min(1, $a)));
    }
}
