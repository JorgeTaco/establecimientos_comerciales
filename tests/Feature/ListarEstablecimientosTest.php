<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarEstablecimientosTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_una_lista_vacia_si_no_hay_establecimientos(): void
    {
        $this->getJson('/api/establecimientos')
            ->assertOk()
            ->assertExactJson(['establecimientos' => []]);
    }

    public function test_devuelve_el_listado_ordenado_segun_el_contrato(): void
    {
        $sinUbicacion = Establecimiento::query()->create([
            'nombre' => 'Tienda Demo Pinos',
            'tipo' => 'tienda',
            'parroquia' => 'Tanicuchí',
        ]);
        $conUbicacion = Establecimiento::query()->create([
            'nombre' => 'Minimercado Demo Amanecer',
            'tipo' => 'minimercado',
            'parroquia' => 'Mulaló',
            'latitud' => -0.775,
            'longitud' => -78.58,
            'recepcion_proveedores' => 'recibe_propuestas',
        ]);

        $this->getJson('/api/establecimientos')
            ->assertOk()
            ->assertExactJson([
                'establecimientos' => [
                    [
                        'id' => (string) $conUbicacion->id,
                        'nombre' => 'Minimercado Demo Amanecer',
                        'tipo' => 'minimercado',
                        'parroquia' => 'Mulaló',
                        'latitud' => -0.775,
                        'longitud' => -78.58,
                        'recepcionProveedores' => 'recibe_propuestas',
                    ],
                    [
                        'id' => (string) $sinUbicacion->id,
                        'nombre' => 'Tienda Demo Pinos',
                        'tipo' => 'tienda',
                        'parroquia' => 'Tanicuchí',
                        'latitud' => null,
                        'longitud' => null,
                        'recepcionProveedores' => 'por_confirmar',
                    ],
                ],
            ]);
    }
}
