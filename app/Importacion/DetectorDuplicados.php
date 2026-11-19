<?php

namespace App\Importacion;

use App\Models\Establecimiento;
use App\Support\DatosEstablecimiento;
use Illuminate\Support\Str;
use Throwable;

/**
 * Detecta posibles duplicados dentro del mismo archivo y contra la base.
 *
 * La decisión final es manual: el comando solo marca la fila y muestra los
 * candidatos para que Jorge o Brandon revisen antes de fusionar fichas.
 */
class DetectorDuplicados
{
    public const ALTA = 'alta';

    public const MEDIA = 'media';

    public const BAJA = 'baja';

    public function __construct(private DatosEstablecimiento $establecimientos) {}

    /**
     * @param  list<array{fila: int, numero: ?string, nombre: ?string, datos: array<string, mixed>}>  $filas
     * @return array<int, list<array{fila: int, numero: ?string, nombre: ?string, motivos: list<string>, confianza: string}>>
     */
    public function enArchivo(array $filas): array
    {
        $resultado = [];
        $vistos = [];

        foreach ($filas as $indice => $fila) {
            $claves = $this->claves($fila['datos']);
            $candidatos = [];

            foreach ($vistos as $otro) {
                $motivos = $this->comparar($claves, $otro['claves']);

                if ($motivos !== []) {
                    $candidatos[] = [
                        'fila' => $otro['fila'],
                        'numero' => $otro['numero'],
                        'nombre' => $otro['nombre'],
                        'motivos' => $motivos,
                        'confianza' => $this->confianza($motivos),
                    ];
                }
            }

            $resultado[$indice] = $candidatos;
            $vistos[] = [
                'claves' => $claves,
                'fila' => $fila['fila'],
                'numero' => $fila['numero'],
                'nombre' => $fila['nombre'],
            ];
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{id: string, nombre: string, motivos: list<string>}>
     */
    public function enBaseDeDatos(array $datos): array
    {
        try {
            $ids = $this->establecimientos->duplicados($datos);

            if ($ids === []) {
                return [];
            }

            $claves = $this->claves($datos);

            return Establecimiento::query()
                ->whereKey($ids)
                ->with('perfilComercial')
                ->get()
                ->map(function (Establecimiento $establecimiento) use ($claves): array {
                    $perfil = $establecimiento->perfilComercial;
                    $otro = [
                        'nombre' => $this->clave($establecimiento->nombre),
                        'direccion' => $this->clave((string) $perfil?->direccion),
                        'telefono' => preg_replace('/\D/', '', (string) $perfil?->contacto_telefono) ?? '',
                    ];

                    return [
                        'id' => (string) $establecimiento->id,
                        'nombre' => $establecimiento->nombre,
                        'motivos' => $this->comparar($claves, $otro),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            // Si la base no está disponible, el comando puede seguir revisando
            // el archivo; la comprobación contra la base se repetirá al guardar.
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{nombre: string, direccion: string, telefono: string}
     */
    private function claves(array $datos): array
    {
        $perfil = $datos['perfil'] ?? [];

        return [
            'nombre' => $this->clave((string) ($datos['nombre'] ?? '')),
            'direccion' => $this->clave((string) ($perfil['direccion'] ?? '')),
            'telefono' => preg_replace('/\D/', '', (string) ($perfil['contacto_telefono'] ?? '')) ?? '',
        ];
    }

    /**
     * @param  array{nombre: string, direccion: string, telefono: string}  $a
     * @param  array{nombre: string, direccion: string, telefono: string}  $b
     * @return list<string>
     */
    private function comparar(array $a, array $b): array
    {
        $motivos = [];

        if ($a['nombre'] !== '' && $a['nombre'] === $b['nombre']) {
            $motivos[] = 'nombre';
        }

        if ($a['direccion'] !== '' && $a['direccion'] === $b['direccion']) {
            $motivos[] = 'dirección';
        }

        if ($a['telefono'] !== '' && $a['telefono'] === $b['telefono']) {
            $motivos[] = 'teléfono';
        }

        return $motivos;
    }

    /**
     * Un teléfono compartido, o nombre y dirección iguales, es una señal
     * fuerte de duplicado. Compartir solo el nombre o solo la dirección es
     * común en comercios vecinos y no debe bloquear la importación.
     *
     * @param  list<string>  $motivos
     */
    private function confianza(array $motivos): string
    {
        if (in_array('teléfono', $motivos, true)) {
            return self::ALTA;
        }

        if (count($motivos) >= 2) {
            return self::ALTA;
        }

        if (in_array('nombre', $motivos, true)) {
            return self::MEDIA;
        }

        return self::BAJA;
    }

    private function clave(string $valor): string
    {
        $valor = Str::ascii(trim($valor));
        $valor = mb_strtolower($valor, 'UTF-8');
        $valor = preg_replace('/[^a-z0-9]+/u', ' ', $valor) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }
}
