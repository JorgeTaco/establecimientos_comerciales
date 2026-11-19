<?php

namespace App\Importacion;

use App\Support\DatosEstablecimiento;

class ImportadorEstablecimientos
{
    public function __construct(
        private LectorCsv $lector,
        private NormalizadorFilas $normalizador,
        private DatosEstablecimiento $datos,
    ) {}

    public function preparar(string $ruta, int $tamanoLote = 100): array
    {
        $archivo = $this->lector->leer($ruta);
        $lotes = [];
        $filas = [];
        $numeros = [];
        $invalidas = [];
        $duplicados = [];
        $vistas = [];

        foreach ($archivo['filas'] as $fila) {
            $normalizada = $this->normalizador->normalizar($fila['valores'], $fila['numero']);

            if ($normalizada['errores'] !== []) {
                $invalidas[] = ['fila' => $fila['numero'], 'errores' => $normalizada['errores']];

                continue;
            }

            $datos = $normalizada['datos'];
            $clave = $this->claveFila($datos);

            if (isset($vistas[$clave])) {
                $duplicados[] = [
                    'fila' => $fila['numero'],
                    'candidatos' => ['fila:'.$vistas[$clave]],
                ];

                continue;
            }

            $vistas[$clave] = $fila['numero'];
            $candidatos = $this->datos->duplicadosImportacion($datos);
            if ($candidatos !== []) {
                $duplicados[] = ['fila' => $fila['numero'], 'candidatos' => $candidatos];

                continue;
            }

            $filas[] = $datos;
            $numeros[] = $fila['numero'];

            if (count($filas) === $tamanoLote) {
                $lotes[] = ['filas' => $filas, 'numeros' => $numeros];
                $filas = [];
                $numeros = [];
            }
        }

        if ($filas !== []) {
            $lotes[] = ['filas' => $filas, 'numeros' => $numeros];
        }

        return [
            'columnas' => $archivo['columnas'],
            'sin_mapear' => $archivo['sin_mapear'],
            'totales' => [
                'filas' => count($archivo['filas']),
                'validas' => array_sum(array_map(fn ($lote) => count($lote['filas']), $lotes)),
                'invalidas' => count($invalidas),
                'duplicados' => count($duplicados),
                'lotes' => count($lotes),
            ],
            'lotes' => $lotes,
            'invalidas' => $invalidas,
            'duplicados' => $duplicados,
        ];
    }

    private function claveFila(array $datos): string
    {
        return implode('|', [
            mb_strtolower($datos['nombre'] ?? '', 'UTF-8'),
            mb_strtolower($datos['perfil']['direccion'] ?? '', 'UTF-8'),
            (string) ($datos['perfil']['contacto_telefono'] ?? ''),
        ]);
    }
}
