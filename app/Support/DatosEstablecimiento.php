<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\PerfilComercial;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DatosEstablecimiento
{
    public function reglas(bool $nuevo, bool $importacion = false): array
    {
        $presencia = $nuevo ? 'required' : 'sometimes';

        $reglas = [
            'nombre' => [$presencia, 'required', 'string', 'max:255'],
            'tipo' => [$presencia, 'required', 'string', 'max:80'],
            'parroquia' => [$presencia, 'required', 'string', 'max:255'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'recepcion_proveedores' => ['sometimes', 'required', Rule::in(FlujoComercial::RECEPCION)],
            'categorias' => ['sometimes', 'array', 'list', 'max:30'],
            'categorias.*' => ['required', 'distinct', Rule::exists('categorias', 'id')->where('activa', true)],
            'perfil' => ['sometimes', 'array:foto_url,direccion,canton,sector,productos_buscados,tiene_refrigeracion,contacto_nombre,contacto_telefono,contacto_email,canales_contacto,autorizar_contacto,horario_atencion,horario_proveedores,requisitos,modalidad_compra,pedido_inicial,devoluciones,decision_compra'],
            'perfil.foto_url' => ['nullable', 'url:http,https', 'max:2048'],
            'perfil.direccion' => ['nullable', 'string', 'max:255'],
            'perfil.canton' => ['nullable', 'string', 'max:255'],
            'perfil.sector' => ['nullable', 'string', 'max:255'],
            'perfil.productos_buscados' => ['nullable', 'array', 'list', 'max:30'],
            'perfil.productos_buscados.*' => ['required', 'string', 'max:255'],
            'perfil.tiene_refrigeracion' => ['nullable', 'boolean'],
            'perfil.contacto_nombre' => ['nullable', 'string', 'max:255'],
            'perfil.contacto_telefono' => ['nullable', 'regex:/\A\+?[0-9 ()-]{7,39}\z/'],
            'perfil.contacto_email' => ['nullable', 'email', 'max:255'],
            'perfil.canales_contacto' => ['nullable', 'array', 'list', 'max:3'],
            'perfil.canales_contacto.*' => ['required', 'distinct', Rule::in(['telefono', 'whatsapp', 'email'])],
            'perfil.autorizar_contacto' => ['sometimes', 'required', 'boolean'],
            'perfil.horario_atencion' => ['nullable', 'string', 'max:255'],
            'perfil.horario_proveedores' => ['nullable', 'string', 'max:255'],
            'perfil.requisitos' => ['nullable', 'string', 'max:4000'],
            'perfil.modalidad_compra' => ['nullable', Rule::in(['contado', 'credito', 'consignacion'])],
            'perfil.pedido_inicial' => ['nullable', 'integer', 'between:1,1000000000'],
            'perfil.devoluciones' => ['nullable', 'string', 'max:4000'],
            'perfil.decision_compra' => ['nullable', Rule::in(['sucursal', 'oficina_central'])],
        ];

        if ($importacion) {
            $reglas['interes_encuesta'] = ['nullable', 'boolean'];
        }

        return $reglas;
    }

    public function guardar(
        array $datos, ?Establecimiento $establecimiento = null, ?int $responsableId = null, ?int $propietario = null
    ): Establecimiento {
        [$establecimiento] = $this->persistir($datos, $establecimiento, $responsableId, false, $propietario);

        return $establecimiento;
    }

    public function importar(array $datos): array
    {
        return Cache::store('database')->lock('importacion:establecimientos', 30)
            ->block(5, fn () => $this->persistir($datos, null, null, true));
    }

    public function duplicados(array $datos): array
    {
        return $this->posiblesDuplicados($datos, $datos['perfil'] ?? []);
    }

    public function duplicadosImportacion(array $datos): array
    {
        $perfil = $datos['perfil'] ?? [];

        return $this->posiblesDuplicados($datos, $perfil, paraImportar: true);
    }

    private function persistir(
        array $datos, ?Establecimiento $establecimiento, ?int $responsableId, bool $importacion, ?int $propietario = null
    ): array {
        return DB::transaction(function () use ($datos, $establecimiento, $responsableId, $importacion, $propietario) {
            if ($establecimiento !== null) {
                $establecimiento = Establecimiento::query()->lockForUpdate()->findOrFail($establecimiento->id);
                abort_if($propietario !== null && (int) $establecimiento->perfilComercial?->user_id !== $propietario, 404);
            }

            $base = Arr::only($datos, ['nombre', 'tipo', 'parroquia', 'latitud', 'longitud', 'recepcion_proveedores']);
            $coordenadas = $base + ($establecimiento?->only(['latitud', 'longitud']) ?? []);
            if ((($coordenadas['latitud'] ?? null) === null) !== (($coordenadas['longitud'] ?? null) === null)) {
                throw ValidationException::withMessages(['latitud' => 'Indica las dos coordenadas o deja ambas vacías.']);
            }

            if ($importacion) {
                $duplicados = $this->duplicadosImportacion($datos);
                if ($duplicados !== []) {
                    return [null, $duplicados];
                }
                $base['recepcion_proveedores'] = 'por_confirmar';
            }

            $establecimiento ??= new Establecimiento;
            $establecimiento->fill($base)->save();
            $perfil = $establecimiento->perfilComercial()->firstOrNew();
            if ($importacion) {
                $perfil->interes_encuesta = $datos['interes_encuesta'] ?? null;
            }
            $datosPerfil = $datos['perfil'] ?? [];
            $this->configurarContacto($perfil, $datosPerfil, $importacion);
            if ($responsableId !== null) {
                $perfil->user_id = $responsableId;
            }
            if ($establecimiento->wasRecentlyCreated || $establecimiento->wasChanged() || $perfil->isDirty() || isset($datos['categorias'])) {
                $perfil->verificado_en = null;
            }
            $establecimiento->perfilComercial()->save($perfil);

            if (array_key_exists('categorias', $datos)) {
                $establecimiento->categorias()->sync($datos['categorias']);
            }

            return [$establecimiento->load('perfilComercial', 'categorias'), []];
        });
    }

    private function configurarContacto(PerfilComercial $perfil, array $datos, bool $importacion): void
    {
        $autorizar = $datos['autorizar_contacto'] ?? null;
        if (isset($datos['contacto_telefono'])) {
            $datos['contacto_telefono'] = preg_replace('/[ ()-]/', '', $datos['contacto_telefono']);
        }
        $perfil->fill(Arr::except($datos, ['autorizar_contacto']));
        if ($importacion || ($autorizar !== null && ! $autorizar) || $perfil->isDirty([
            'contacto_nombre', 'contacto_telefono', 'contacto_email', 'canales_contacto',
        ])) {
            $perfil->contacto_autorizado_en = null;
        }

        if (! $importacion && $autorizar) {
            $canales = $perfil->canales_contacto ?? [];
            if ($canales === []
                || (array_intersect($canales, ['telefono', 'whatsapp']) && $perfil->contacto_telefono === null)
                || (in_array('email', $canales, true) && $perfil->contacto_email === null)) {
                throw ValidationException::withMessages(['perfil.canales_contacto' => 'Completa los datos de los canales autorizados.']);
            }
            $perfil->contacto_autorizado_en = now();
        }
    }

    private function posiblesDuplicados(array $base, array $perfil, bool $paraImportar = false): array
    {
        $direccion = $perfil['direccion'] ?? null;
        $telefono = ltrim(preg_replace('/[ ()-]/', '', $perfil['contacto_telefono'] ?? ''), '+');
        $consulta = Establecimiento::query()
            ->where(function ($query) use ($base, $direccion, $telefono, $paraImportar) {
                $query->where(function ($q) use ($base, $direccion, $telefono, $paraImportar) {
                    $q->where('nombre', $base['nombre']);
                    if ($paraImportar && filled($direccion)) {
                        $q->whereHas('perfilComercial', fn ($p) => $p->where('direccion', $direccion));
                    } elseif ($paraImportar) {
                        // Las fichas sin dirección aún deben poder reintentarse.
                        $q->where('tipo', $base['tipo'])->where('parroquia', $base['parroquia'])
                            ->whereDoesntHave('perfilComercial', fn ($p) => $p->whereNotNull('direccion'));
                        if ($telefono !== '') {
                            $q->whereDoesntHave('perfilComercial', fn ($p) => $p->whereNotNull('contacto_telefono'));
                        }
                    }
                });
                if (! $paraImportar && filled($direccion)) {
                    $query->orWhereHas('perfilComercial', fn ($p) => $p->where('direccion', $direccion));
                }
                if ($telefono !== '') {
                    $query->orWhereHas('perfilComercial', fn ($p) => $p->whereIn('contacto_telefono', [$telefono, '+'.$telefono]));
                }
            });

        return $consulta->orderBy('id')->limit(10)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
