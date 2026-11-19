<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\ReporteEstablecimiento;
use App\Models\User;
use App\Support\CacheComercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConsultasEscalablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cursor_alfabetico_y_mapa_comparten_filtros_y_conteos(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->comercio('Mercado con el mismo nombre', $i === 0 ? null : -0.93, $i === 0 ? null : -78.61);
        }
        $this->comercio('Otro sector', -0.92, -78.6, 'Lasso');
        $ruta = '/api/establecimientos/buscar?parroquia=Mulal%C3%B3';

        $primera = $this->getJson($ruta.'&limite=24&facetas=1')->assertOk()
            ->assertJsonCount(24, 'establecimientos')
            ->assertJsonPath('totalCoincidencias', 30)
            ->assertJsonPath('totalDirectorio', 31)
            ->assertJsonPath('facetas.tipo.0.cantidad', 30);
        $this->assertIsString($primera->json('siguienteCursor'));
        $segunda = $this->getJson($ruta.'&limite=24&cursor='.$primera->json('siguienteCursor'))->assertOk()
            ->assertJsonCount(6, 'establecimientos')
            ->assertJsonPath('siguienteCursor', null);
        $conCursor = array_merge(array_column($primera->json('establecimientos'), 'id'), array_column($segunda->json('establecimientos'), 'id'));
        $sinCursor = $this->getJson($ruta)->assertOk()->assertJsonCount(30, 'establecimientos')->assertJsonMissingPath('siguienteCursor');
        $this->assertSame(array_column($sinCursor->json('establecimientos'), 'id'), $conCursor);

        $this->getJson($ruta.'&modo=mapa')->assertOk()->assertJsonCount(29, 'establecimientos')
            ->assertJsonMissingPath('establecimientos.0.foto_url')
            ->assertJsonMissingPath('establecimientos.0.interesDeclarado');
        $this->getJson($ruta.'&limite=24&cursor=malformado')->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_el_rectangulo_geografico_conserva_el_radio_exacto_en_el_meridiano_y_los_polos(): void
    {
        $this->comercio('Centro', 0, 179.99);
        $this->comercio('Vecino del otro lado', 0, -179.99);
        $this->comercio('Esquina fuera del radio', 0.04, -179.97);
        $this->comercio('Polo opuesto', 89.99, -120);
        $this->comercio('Polo lejano', 89.95, -120);

        $this->getJson('/api/establecimientos/buscar?facetas=1&latitud=0&longitud=179.99&distancia_km=5')
            ->assertOk()->assertJsonCount(2, 'establecimientos')
            ->assertJsonPath('establecimientos.1.nombre', 'Vecino del otro lado')
            ->assertJsonPath('facetas.tipo.0.cantidad', 2);
        $this->getJson('/api/establecimientos/buscar?modo=mapa&latitud=89.99&longitud=60&distancia_km=3')
            ->assertOk()->assertJsonCount(1, 'establecimientos')
            ->assertJsonPath('establecimientos.0.nombre', 'Polo opuesto');
    }

    public function test_la_cache_se_invalida_al_cambiar_catalogo_directorio_e_indicadores(): void
    {
        $ficha = $this->comercio('Tienda inicial', -0.93, -78.61);
        $emprendedor = User::factory()->create();
        $oportunidad = $emprendedor->oportunidades()->create(['establecimiento_id' => $ficha->id]);
        $admin = User::factory()->create(['rol' => 'administrador']);
        $portada = '/api/establecimientos/buscar?limite=24&facetas=1';

        $this->actingAs($admin)->getJson('/api/admin/indicadores')->assertOk()
            ->assertJsonPath('indicadores.puntos_venta_activos', 0);
        $this->assertTrue(Cache::has(CacheComercial::INDICADORES));
        $this->actingAs($emprendedor)->patchJson('/api/oportunidades/'.$oportunidad->id, ['estado' => 'punto_venta_activo'])->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/indicadores')->assertOk()
            ->assertJsonPath('indicadores.puntos_venta_activos', 1)
            ->assertJsonPath('indicadores.emprendedores_con_primer_pedido', 1);

        $this->getJson('/api/categorias')->assertOk()->assertJsonCount(0, 'categorias');
        $this->getJson($portada)->assertOk()->assertJsonPath('totalCoincidencias', 1);
        $this->assertTrue(Cache::has(CacheComercial::clavePortada(false)));
        $this->postJson('/api/admin/categorias', ['nombre' => 'Lácteos'])->assertCreated();
        $this->assertFalse(Cache::has(CacheComercial::clavePortada(false)));
        $this->getJson('/api/categorias')->assertOk()->assertJsonPath('categorias.0.nombre', 'Lácteos');
        $this->getJson($portada)->assertOk()->assertJsonPath('totalCoincidencias', 1);
        $this->postJson('/api/establecimientos', ['nombre' => 'Tienda nueva', 'tipo' => 'tienda', 'parroquia' => 'Mulaló'])->assertCreated();
        $this->getJson($portada)->assertOk()->assertJsonPath('totalCoincidencias', 2);
    }

    public function test_usuarios_y_reportes_permiten_cursores_y_conservan_el_contrato_anterior(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        User::factory(52)->create();
        $ficha = $this->comercio('Tienda de prueba', -0.93, -78.61);
        $reportes = array_fill(0, 52, ['user_id' => $admin->id, 'establecimiento_id' => $ficha->id, 'descripcion' => 'Revisión ficticia']);
        ReporteEstablecimiento::query()->insert($reportes);

        foreach (['usuarios' => 3, 'reportes' => 2] as $recurso => $restantes) {
            $ruta = '/api/admin/'.$recurso;
            $this->actingAs($admin)->getJson($ruta.'?page=2')->assertOk()
                ->assertJsonPath($recurso.'.current_page', 2)->assertJsonCount($restantes, $recurso.'.data');
            $primera = $this->getJson($ruta.'?por_cursor=1')->assertOk()->assertJsonCount(50, $recurso.'.data');
            $cursor = $primera->json($recurso.'.next_cursor');
            $this->assertIsString($cursor);
            $segunda = $this->getJson($ruta.'?por_cursor=1&cursor='.$cursor)->assertOk()
                ->assertJsonCount($restantes, $recurso.'.data')
                ->assertJsonPath($recurso.'.next_cursor', null);
            $this->assertEmpty(array_intersect(array_column($primera->json($recurso.'.data'), 'id'), array_column($segunda->json($recurso.'.data'), 'id')));
        }
    }

    private function comercio(string $nombre, ?float $latitud, ?float $longitud, string $parroquia = 'Mulaló'): Establecimiento
    {
        return Establecimiento::query()->create([
            'nombre' => $nombre, 'tipo' => 'tienda', 'parroquia' => $parroquia,
            'latitud' => $latitud, 'longitud' => $longitud,
        ]);
    }
}
