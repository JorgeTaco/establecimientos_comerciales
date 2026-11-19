<?php

namespace App\Importacion;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee archivos .xlsx y .csv sin dependencias externas.
 *
 * Devuelve una lista de filas donde el índice coincide con el número de fila
 * del archivo menos uno (la fila 1 del Excel está en el índice 0).
 */
class LectorHojaCalculo
{
    private string $ruta;

    private string $extension;

    public function __construct(string $ruta)
    {
        $rutaReal = realpath($ruta);

        if ($rutaReal === false || ! is_file($rutaReal) || ! is_readable($rutaReal)) {
            throw new RuntimeException("No se puede leer el archivo: {$ruta}");
        }

        $this->ruta = $rutaReal;
        $this->extension = mb_strtolower(pathinfo($rutaReal, PATHINFO_EXTENSION));
    }

    /**
     * @return list<string>
     */
    public function hojas(): array
    {
        if ($this->extension === 'csv') {
            return ['CSV'];
        }

        $zip = $this->abrirZip();

        try {
            return array_map(fn (array $hoja) => $hoja['nombre'], $this->hojasXlsx($zip));
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<list<string>>
     */
    public function leer(?string $hoja = null): array
    {
        return match ($this->extension) {
            'csv' => $this->leerCsv(),
            'xlsx' => $this->leerXlsx($hoja),
            default => throw new RuntimeException('Formato no soportado. Usa un archivo .xlsx o .csv.'),
        };
    }

    private function abrirZip(): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($this->ruta) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo .xlsx. Verifica que no esté dañado.');
        }

        return $zip;
    }

    /**
     * @return list<list<string>>
     */
    private function leerXlsx(?string $hoja): array
    {
        $zip = $this->abrirZip();

        try {
            $hojas = $this->hojasXlsx($zip);
            $seleccionada = $this->seleccionarHoja($hojas, $hoja);
            $rutaHoja = $seleccionada['ruta'];
            $xmlHoja = $zip->getFromName($rutaHoja);

            if ($xmlHoja === false) {
                throw new RuntimeException("No se encontró la hoja «{$seleccionada['nombre']}» dentro del archivo.");
            }

            $compartidos = $this->cadenasCompartidas($zip);
            $sheet = $this->xml($xmlHoja, 'la hoja de cálculo');
            $filas = [];

            foreach ($sheet->sheetData->row as $filaXml) {
                $numero = (int) $filaXml['r'];

                if ($numero < 1) {
                    $numero = $filas === [] ? 1 : max(array_keys($filas)) + 2;
                }

                $valores = [];
                $siguiente = 0;

                foreach ($filaXml->c as $celda) {
                    $referencia = (string) $celda['r'];
                    $indice = $referencia === ''
                        ? $siguiente
                        : $this->indiceColumna($referencia);

                    $siguiente = $indice + 1;
                    $valores[$indice] = $this->valorCelda($celda, $compartidos);
                }

                $filas[$numero - 1] = $this->normalizarFila($valores);
            }

            if ($filas === []) {
                return [];
            }

            $resultado = [];
            $ultima = max(array_keys($filas));

            for ($i = 0; $i <= $ultima; $i++) {
                $resultado[$i] = $filas[$i] ?? [];
            }

            return $resultado;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{nombre: string, ruta: string}>
     */
    private function hojasXlsx(ZipArchive $zip): array
    {
        $xmlWorkbook = $zip->getFromName('xl/workbook.xml');

        if ($xmlWorkbook === false) {
            throw new RuntimeException('El archivo no parece ser un .xlsx válido: falta xl/workbook.xml.');
        }

        $workbook = $this->xml($xmlWorkbook, 'el libro de Excel');
        $relaciones = [];

        if (($xmlRels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $rels = $this->xml($xmlRels, 'las relaciones del libro');

            foreach ($rels->Relationship as $relacion) {
                $relaciones[(string) $relacion['Id']] = $this->resolverRuta('xl', (string) $relacion['Target']);
            }
        }

        $hojas = [];
        $indice = 1;

        foreach ($workbook->sheets->sheet as $hoja) {
            $idRelacion = (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $ruta = $relaciones[$idRelacion] ?? "xl/worksheets/sheet{$indice}.xml";

            $hojas[] = [
                'nombre' => (string) $hoja['name'],
                'ruta' => $ruta,
            ];

            $indice++;
        }

        return $hojas;
    }

    /**
     * @param  list<array{nombre: string, ruta: string}>  $hojas
     * @return array{nombre: string, ruta: string}
     */
    private function seleccionarHoja(array $hojas, ?string $hoja): array
    {
        if ($hojas === []) {
            throw new RuntimeException('El archivo no contiene hojas.');
        }

        if ($hoja === null || $hoja === '') {
            return $hojas[0];
        }

        if (ctype_digit($hoja)) {
            $indice = (int) $hoja - 1;

            if (! isset($hojas[$indice])) {
                throw new RuntimeException("La hoja número {$hoja} no existe. Hay ".count($hojas).' hojas.');
            }

            return $hojas[$indice];
        }

        foreach ($hojas as $candidata) {
            if (mb_strtolower($candidata['nombre']) === mb_strtolower($hoja)) {
                return $candidata;
            }
        }

        throw new RuntimeException("No existe la hoja «{$hoja}». Hojas disponibles: ".implode(', ', array_column($hojas, 'nombre')));
    }

    /**
     * @return list<string>
     */
    private function cadenasCompartidas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sst = $this->xml($xml, 'las cadenas compartidas');
        $cadenas = [];

        foreach ($sst->si as $si) {
            $texto = '';

            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $texto .= (string) $t;
            }

            $cadenas[] = $texto;
        }

        return $cadenas;
    }

    /**
     * @param  list<string>  $compartidos
     */
    private function valorCelda(SimpleXMLElement $celda, array $compartidos): string
    {
        $tipo = (string) $celda['t'];

        if ($tipo === 's') {
            return $compartidos[(int) $celda->v] ?? '';
        }

        if ($tipo === 'inlineStr') {
            $texto = '';

            foreach ($celda->xpath('.//*[local-name()="t"]') as $t) {
                $texto .= (string) $t;
            }

            return $texto;
        }

        return (string) $celda->v;
    }

    /**
     * @param  array<int, string>  $valores
     * @return list<string>
     */
    private function normalizarFila(array $valores): array
    {
        if ($valores === []) {
            return [];
        }

        $fila = array_fill(0, max(array_keys($valores)) + 1, '');

        foreach ($valores as $indice => $valor) {
            $fila[$indice] = $valor;
        }

        return $fila;
    }

    private function indiceColumna(string $referencia): int
    {
        $letras = preg_replace('/\d+/', '', $referencia);
        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord(mb_strtoupper($letra)) - 64);
        }

        return max(0, $indice - 1);
    }

    private function resolverRuta(string $base, string $destino): string
    {
        $destino = str_replace('\\', '/', $destino);

        if (str_starts_with($destino, '/')) {
            return ltrim($destino, '/');
        }

        if (str_starts_with($destino, 'xl/')) {
            return $destino;
        }

        $partes = explode('/', $base.'/'.$destino);
        $resueltas = [];

        foreach ($partes as $parte) {
            if ($parte === '..') {
                array_pop($resueltas);
            } elseif ($parte !== '.' && $parte !== '') {
                $resueltas[] = $parte;
            }
        }

        return implode('/', $resueltas);
    }

    private function xml(string $contenido, string $descripcion): SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido);

        if ($xml === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);

            throw new RuntimeException("No se pudo interpretar {$descripcion}.");
        }

        libxml_use_internal_errors($anterior);

        return $xml;
    }

    /**
     * @return list<list<string>>
     */
    private function leerCsv(): array
    {
        $contenido = file_get_contents($this->ruta);

        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo CSV.');
        }

        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        $primeraLinea = strtok($contenido, "\r\n") ?: '';
        $delimitador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';

        $memoria = fopen('php://temp', 'r+');

        if ($memoria === false) {
            throw new RuntimeException('No se pudo preparar la lectura del CSV.');
        }

        fwrite($memoria, $contenido);
        rewind($memoria);

        $filas = [];

        while (($fila = fgetcsv($memoria, 0, $delimitador, '"', '')) !== false) {
            $filas[] = array_map(
                fn ($valor) => $valor === null ? '' : trim((string) $valor),
                $fila
            );
        }

        fclose($memoria);

        return $filas;
    }
}
