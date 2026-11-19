<?php

namespace App\Importacion;

use InvalidArgumentException;

class LectorCsv
{
    private const DELIMITADORES = [',', ';', "\t", '|'];

    public function leer(string $ruta): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new InvalidArgumentException("No se puede leer el archivo: {$ruta}");
        }

        $archivo = fopen($ruta, 'rb');
        if ($archivo === false) {
            throw new InvalidArgumentException("No se puede abrir el archivo: {$ruta}");
        }

        try {
            $primeraLinea = fgets($archivo);
            if ($primeraLinea === false) {
                throw new InvalidArgumentException('El archivo está vacío.');
            }

            $delimitador = $this->detectarDelimitador($primeraLinea);
            rewind($archivo);

            $encabezados = fgetcsv($archivo, 0, $delimitador, '"', '\\');
            if ($encabezados === false) {
                throw new InvalidArgumentException('No se pudieron leer los encabezados.');
            }

            $encabezados = array_map(fn ($valor) => $this->texto($valor), $encabezados);
            $mapa = MapaColumnas::desdeEncabezados($encabezados);
            if ($mapa['columnas'] === []) {
                throw new InvalidArgumentException('Ningún encabezado coincide con las columnas esperadas.');
            }

            $filas = [];
            while (($valores = fgetcsv($archivo, 0, $delimitador, '"', '\\')) !== false) {
                $fila = [];
                foreach ($valores as $indice => $valor) {
                    if (! isset($mapa['columnas'][$indice])) {
                        continue;
                    }
                    $fila[$mapa['columnas'][$indice]] = $this->texto($valor);
                }

                if ($fila === [] || $this->estaVacia($fila)) {
                    continue;
                }

                $filas[] = ['numero' => count($filas), 'valores' => $fila];
            }

            return [
                'columnas' => array_values(array_unique(array_values($mapa['columnas']))),
                'sin_mapear' => $mapa['sin_mapear'],
                'filas' => $filas,
            ];
        } finally {
            fclose($archivo);
        }
    }

    private function detectarDelimitador(string $linea): string
    {
        $mejor = ',';
        $maximo = -1;

        foreach (self::DELIMITADORES as $delimitador) {
            $conteo = substr_count($linea, $delimitador);
            if ($conteo > $maximo) {
                $maximo = $conteo;
                $mejor = $delimitador;
            }
        }

        return $mejor;
    }

    private function texto(mixed $valor): string
    {
        $texto = trim((string) $valor);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;

        if ($texto !== '' && ! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        return $texto;
    }

    private function estaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
