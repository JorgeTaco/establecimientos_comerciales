<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Establecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ImportarEstablecimientosTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            is_dir($ruta) ? File::deleteDirectory($ruta) : @unlink($ruta);
        }

        parent::tearDown();
    }

    public function test_importa_csv_valido_y_separa_invalidas_duplicados_y_coordenadas(): void
    {
        Categoria::query()->create(['nombre' => 'Lácteos']);

        $directorio = $this->directorioTemporal();
        $archivo = $this->escribirCsv($directorio, [
            ['N°', 'Nombre del establecimiento', 'Tipo de establecimiento', 'Direccion completa', 'Provincia', 'Canton', 'Parroquia', 'Barrio o sector', 'Telefono de contacto', 'Correo electronico', 'Comercializa lacteos', 'Interes en nuevos proveedores', 'Latitud (GPS)', 'Longitud (GPS)'],
            ['1', 'Tienda Ejemplo', 'Tienda', 'Mulalo, Calle 4 De Octubre', 'Cotopaxi', 'Latacunga', 'Mulalo', 'Centro', '0991234567', 'demo@example.test', 'Si', 'Si', '-0.933', '-78.615'],
            ['2', 'Tienda Ejemplo', 'Tienda', 'Otra Calle', 'Cotopaxi', 'Latacunga', 'Mulalo', 'La Esperanza', '0991234567', 'demo2@example.test', 'No', 'No', '', ''],
            ['3', '', 'Tienda', 'Calle X', 'Cotopaxi', 'Latacunga', 'Mulalo', '', '0999999999', '', 'No', 'No', '', ''],
            ['4', 'Panaderia Alpina', 'Panaderia', 'Calle Y', 'Cotopaxi', 'Latacunga', 'Tanicuchi', '', '0988888888', '', 'Si', 'Si', '0.986182', '-78.582756'],
        ]);
        $reporte = $directorio.'/reporte.json';

        $this->artisan('establecimientos:importar', [
            'archivo' => $archivo,
            '--categoria' => ['Lácteos'],
            '--json' => $reporte,
        ])->assertExitCode(0);

        $datos = json_decode((string) file_get_contents($reporte), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $datos['fila_encabezado']);
        $this->assertSame(4, $datos['resumen']['total']);
        $this->assertSame('importada', $datos['filas'][0]['estado']);
        $this->assertSame('posible_duplicado', $datos['filas'][1]['estado']);
        $this->assertSame('invalida', $datos['filas'][2]['estado']);
        $this->assertSame('revisar', $datos['filas'][3]['estado']);

        $this->assertDatabaseCount('establecimientos', 1);

        $establecimiento = Establecimiento::query()->sole();
        $this->assertSame('Tienda Ejemplo', $establecimiento->nombre);
        $this->assertSame('tienda', $establecimiento->tipo);
        $this->assertSame('Mulaló', $establecimiento->parroquia);
        $this->assertSame('por_confirmar', $establecimiento->recepcion_proveedores);
        $this->assertEqualsWithDelta(-0.933, $establecimiento->latitud, 0.000001);
        $this->assertEqualsWithDelta(-78.615, $establecimiento->longitud, 0.000001);

        $perfil = $establecimiento->perfilComercial;
        $this->assertNotNull($perfil);
        $this->assertSame('Mulaló, Calle 4 De Octubre', $perfil->direccion);
        $this->assertNull($perfil->contacto_nombre);
        $this->assertSame('0991234567', $perfil->contacto_telefono);
        $this->assertSame('demo@example.test', $perfil->contacto_email);
        $this->assertTrue($perfil->interes_encuesta);
        $this->assertNull($perfil->contacto_autorizado_en);
        $this->assertCount(1, $establecimiento->categorias);

        // Una segunda pasada no vuelve a crear la misma ficha.
        $this->artisan('establecimientos:importar', [
            'archivo' => $archivo,
            '--json' => $reporte,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('establecimientos', 1);

        $datos = json_decode((string) file_get_contents($reporte), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('posible_duplicado', $datos['filas'][0]['estado']);
        $this->assertNotEmpty($datos['filas'][0]['candidatos_db']);
    }

    public function test_corrige_latitudes_positivas_con_la_bandera(): void
    {
        $directorio = $this->directorioTemporal();
        $archivo = $this->escribirCsv($directorio, [
            ['N°', 'Nombre del establecimiento', 'Tipo de establecimiento', 'Direccion completa', 'Provincia', 'Canton', 'Parroquia', 'Barrio o sector', 'Telefono de contacto', 'Correo electronico', 'Comercializa lacteos', 'Interes en nuevos proveedores', 'Latitud (GPS)', 'Longitud (GPS)'],
            ['1', 'Panaderia Alpina', 'Panaderia', 'Calle Y', 'Cotopaxi', 'Latacunga', 'Tanicuchi', '', '0988888888', '', 'Si', 'Si', '0.986182', '-78.582756'],
        ]);
        $reporte = $directorio.'/reporte.json';

        $this->artisan('establecimientos:importar', [
            'archivo' => $archivo,
            '--corregir-signo' => true,
            '--json' => $reporte,
        ])->assertExitCode(0);

        $datos = json_decode((string) file_get_contents($reporte), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('importada', $datos['filas'][0]['estado']);
        $this->assertSame('corregida', $datos['filas'][0]['coordenadas']['estado']);

        $this->assertDatabaseHas('establecimientos', [
            'nombre' => 'Panaderia Alpina',
        ]);

        $establecimiento = Establecimiento::query()->sole();
        $this->assertEqualsWithDelta(-0.986182, $establecimiento->latitud, 0.000001);
        $this->assertEqualsWithDelta(-78.582756, $establecimiento->longitud, 0.000001);
    }

    public function test_lee_xlsx_con_encabezado_en_la_cuarta_fila_y_normaliza_los_datos(): void
    {
        $directorio = $this->directorioTemporal();
        $archivo = $directorio.'/encuesta.xlsx';
        $this->escribirXlsx($archivo);
        $reporte = $directorio.'/reporte.json';

        $this->artisan('establecimientos:importar', [
            'archivo' => $archivo,
            '--dry-run' => true,
            '--json' => $reporte,
        ])->assertExitCode(0);

        $datos = json_decode((string) file_get_contents($reporte), true, 512, JSON_THROW_ON_ERROR);
        $fila = $datos['filas'][0];

        $this->assertSame(4, $datos['fila_encabezado']);
        $this->assertSame('valida', $fila['estado']);
        $this->assertSame('minimercado', $fila['datos']['tipo']);
        $this->assertSame('Mulaló', $fila['datos']['parroquia']);
        $this->assertSame('Minimarket "La Esquina"', $fila['datos']['nombre']);
        $this->assertSame('0991234567', $fila['datos']['perfil']['contacto_telefono']);
        $this->assertSame('esquina@example.test', $fila['datos']['perfil']['contacto_email']);
        $this->assertSame(['telefono', 'email'], $fila['datos']['perfil']['canales_contacto']);
        $this->assertFalse($fila['datos']['perfil']['autorizar_contacto']);
        $this->assertSame('por_confirmar', $fila['datos']['recepcion_proveedores']);
        $this->assertTrue($fila['datos']['interes_encuesta']);
        $this->assertEqualsWithDelta(-0.933, $fila['datos']['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-78.615, $fila['datos']['longitud'], 0.000001);
        $this->assertDatabaseCount('establecimientos', 0);
    }

    private function directorioTemporal(): string
    {
        $directorio = storage_path('framework/testing/importacion-'.bin2hex(random_bytes(4)));

        mkdir($directorio, 0775, true);
        $this->temporales[] = $directorio;

        return $directorio;
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function escribirCsv(string $directorio, array $filas): string
    {
        $ruta = $directorio.'/encuesta.csv';
        $memoria = fopen('php://temp', 'r+');

        foreach ($filas as $fila) {
            fputcsv($memoria, $fila, ',', '"', '');
        }

        rewind($memoria);
        file_put_contents($ruta, stream_get_contents($memoria));
        fclose($memoria);

        return $ruta;
    }

    private function escribirXlsx(string $ruta): void
    {
        $encabezados = ['N°', 'Nombre del establecimiento', 'Tipo de establecimiento', 'Direccion completa', 'Provincia', 'Canton', 'Parroquia', 'Barrio o sector', 'Telefono de contacto', 'Correo electronico', 'Latitud (GPS)', 'Longitud (GPS)', 'Comercializa lacteos', 'Interes en nuevos proveedores'];
        $valores = ['1', 'Minimarket "La Esquina"', 'Minimarket', 'Mulalo, Calle 4 De Octubre', 'Cotopaxi', 'Latacunga', 'Mulalo', 'Centro', '0991234567', 'esquina@example.test', '-0.933', '-78.615', 'Si', 'Si'];

        $filasXml = '<row r="1"><c r="A1" t="inlineStr"><is><t>Encuesta de establecimientos</t></is></c></row>'
            .'<row r="2"><c r="A2" t="inlineStr"><is><t>Datos del levantamiento</t></is></c></row>'
            .'<row r="4">'.$this->filaXml(4, $encabezados).'</row>'
            .'<row r="5">'.$this->filaXml(5, $valores).'</row>';

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Respuestas" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$filasXml.'</sheetData></worksheet>');
        $zip->close();
    }

    /**
     * @param  list<string>  $valores
     */
    private function filaXml(int $numero, array $valores): string
    {
        $xml = '';

        foreach ($valores as $indice => $valor) {
            $columna = '';

            for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
                $columna = chr(65 + (($n - 1) % 26)).$columna;
            }

            $referencia = $columna.$numero;
            $xml .= '<c r="'.$referencia.'" t="inlineStr"><is><t>'.htmlspecialchars($valor, ENT_XML1).'</t></is></c>';
        }

        return $xml;
    }
}
