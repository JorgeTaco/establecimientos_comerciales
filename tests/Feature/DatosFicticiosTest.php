<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Establecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatosFicticiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_seeders_publican_los_tres_estados_del_ejemplo(): void
    {
        $this->seed();

        $this->assertDatabaseCount('categorias', 9);
        $this->assertDatabaseCount('establecimientos', 3);
        $this->assertDatabaseCount('perfiles_comerciales', 3);
        foreach (['Leche', 'Queso', 'Yogurt', 'Mantequilla'] as $nombre) {
            $this->assertDatabaseHas('categorias', ['nombre' => $nombre, 'activa' => true]);
        }

        $ejemplo = json_decode(file_get_contents(base_path('datos/ejemplos/establecimientos.json')), true);
        $this->assertCount(3, $ejemplo['establecimientos']);

        foreach ($ejemplo['establecimientos'] as $ficha) {
            $this->assertDatabaseHas('establecimientos', [
                'nombre' => $ficha['nombre'],
                'tipo' => $ficha['tipo'],
                'parroquia' => $ficha['parroquia'],
                'latitud' => $ficha['latitud'],
                'longitud' => $ficha['longitud'],
                'recepcion_proveedores' => $ficha['recepcionProveedores'],
            ]);
        }

        $this->assertDatabaseHas('perfiles_comerciales', ['interes_encuesta' => 1]);
        $this->assertDatabaseHas('perfiles_comerciales', ['interes_encuesta' => 0]);
        $categoria = Categoria::query()->where('nombre', 'Lácteos')->firstOrFail();
        $establecimiento = Establecimiento::query()->where('nombre', 'Tienda Demo Amanecer')->firstOrFail();
        $this->assertDatabaseHas('categoria_establecimiento', [
            'categoria_id' => $categoria->id,
            'establecimiento_id' => $establecimiento->id,
        ]);
    }
}
