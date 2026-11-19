<?php

namespace Tests\Unit;

use App\Importacion\DetectorDuplicados;
use App\Importacion\RevisorCoordenadas;
use App\Support\DatosEstablecimiento;
use PHPUnit\Framework\TestCase;

class ImportacionTest extends TestCase
{
    public function test_revisor_clasifica_las_coordenadas(): void
    {
        $revisor = new RevisorCoordenadas;

        $sin = $revisor->revisar(null, null);
        $this->assertSame(RevisorCoordenadas::SIN_COORDENADAS, $sin['estado']);
        $this->assertFalse($sin['requiere_revision']);

        $incompletas = $revisor->revisar(-0.93, null);
        $this->assertSame(RevisorCoordenadas::INCOMPLETAS, $incompletas['estado']);
        $this->assertTrue($incompletas['requiere_revision']);

        $fuera = $revisor->revisar(-100, -78);
        $this->assertSame(RevisorCoordenadas::FUERA_DE_RANGO, $fuera['estado']);
        $this->assertTrue($fuera['requiere_revision']);

        $signo = $revisor->revisar(0.986182, -78.582756);
        $this->assertSame(RevisorCoordenadas::POSIBLE_SIGNO, $signo['estado']);
        $this->assertTrue($signo['requiere_revision']);
        $this->assertEqualsWithDelta(-0.986182, $revisor->corregirSigno(0.986182), 0.000001);

        $zona = $revisor->revisar(-12.0, -77.0);
        $this->assertSame(RevisorCoordenadas::FUERA_DE_ZONA, $zona['estado']);

        $ok = $revisor->revisar(-0.933, -78.615);
        $this->assertSame(RevisorCoordenadas::OK, $ok['estado']);
        $this->assertFalse($ok['requiere_revision']);
    }

    public function test_detector_distingue_duplicados_fuertes_de_coincidencias_leves(): void
    {
        $detector = new DetectorDuplicados(new DatosEstablecimiento);

        $filas = [
            $this->fila(5, 'Tienda Uno', 'Calle 1', '0991111111'),
            $this->fila(6, 'Tienda Dos', 'Calle 1', '0992222222'),
            $this->fila(7, 'Tienda Tres', 'Calle 2', '0991111111'),
            $this->fila(8, 'Tienda Uno', 'Calle 3', '0993333333'),
            $this->fila(9, 'Tienda Uno', 'Calle 1', '0994444444'),
        ];

        $resultado = $detector->enArchivo($filas);

        $this->assertSame([], $resultado[0]);

        // Misma dirección y distinto nombre/teléfono: coincidencia leve.
        $this->assertSame([DetectorDuplicados::BAJA], array_column($resultado[1], 'confianza'));

        // Mismo teléfono: duplicado fuerte.
        $this->assertSame([DetectorDuplicados::ALTA], array_column($resultado[2], 'confianza'));

        // Mismo nombre y distinta dirección: revisión media.
        $this->assertSame([DetectorDuplicados::MEDIA], array_column($resultado[3], 'confianza'));

        // Mismo nombre y dirección: duplicado fuerte.
        $confianzas = array_column($resultado[4], 'confianza');
        $this->assertContains(DetectorDuplicados::ALTA, $confianzas);
        $this->assertContains(DetectorDuplicados::MEDIA, $confianzas);
    }

    /**
     * @return array{fila: int, numero: string, nombre: string, datos: array<string, mixed>}
     */
    private function fila(int $fila, string $nombre, string $direccion, string $telefono): array
    {
        return [
            'fila' => $fila,
            'numero' => (string) $fila,
            'nombre' => $nombre,
            'datos' => [
                'nombre' => $nombre,
                'perfil' => [
                    'direccion' => $direccion,
                    'contacto_telefono' => $telefono,
                ],
            ],
        ];
    }
}
