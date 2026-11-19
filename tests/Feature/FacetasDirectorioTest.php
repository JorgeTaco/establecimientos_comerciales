<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Establecimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FacetasDirectorioTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $lacteos;

    private Categoria $bebidas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lacteos = Categoria::query()->create(['nombre' => 'Lácteos', 'activa' => true]);
        $this->bebidas = Categoria::query()->create(['nombre' => 'Bebidas', 'activa' => true]);
        $inactiva = Categoria::query()->create(['nombre' => 'Inactiva', 'activa' => false]);

        $this->comercio('Mercado A', 'tienda', 'Mulaló', 'recibe_propuestas', [$this->lacteos->id, $this->bebidas->id, $inactiva->id], false, -0.775);
        $this->comercio('Mercado B', 'minimercado', 'Mulaló', 'por_confirmar', [$this->lacteos->id], true, -0.825);
        $this->comercio('Mercado C', 'tienda', 'Tanicuchí', 'recibe_propuestas', [$this->bebidas->id], true, null);
        $this->comercio('Panadería', 'tienda', 'Lasso', 'recibe_propuestas', [$this->lacteos->id], null, -0.775);
    }

    public function test_cada_faceta_omite_solo_su_propio_filtro(): void
    {
        $respuesta = $this->buscar(['q' => 'Mercado', 'tipo' => 'tienda', 'parroquia' => 'Mulaló'])
            ->assertJsonPath('totalDirectorio', 4)
            ->assertJsonCount(1, 'establecimientos')
            ->assertJsonPath('establecimientos.0.nombre', 'Mercado A')
            ->assertJsonMissingPath('facetas.interes_declarado');

        $this->assertEquals(['tienda' => 1, 'minimercado' => 1], $this->conteos($respuesta, 'tipo'));
        $this->assertEquals(['Mulaló' => 1, 'Tanicuchí' => 1], $this->conteos($respuesta, 'parroquia'));
        $this->assertSame([$this->bebidas->id => 1, $this->lacteos->id => 1], $this->conteos($respuesta, 'categoria_id'));

        $respuesta = $this->buscar(['q' => 'Mercado', 'parroquia' => 'Mulaló', 'recepcionProveedores' => 'recibe_propuestas']);
        $this->assertSame(['recibe_propuestas' => 1, 'por_confirmar' => 1], $this->conteos($respuesta, 'recepcionProveedores'));
    }

    public function test_una_combinacion_vacia_aun_ofrece_alternativas_para_recuperarse(): void
    {
        $respuesta = $this->buscar(['q' => 'Mercado', 'parroquia' => 'Tanicuchí', 'categoria_id' => $this->lacteos->id])
            ->assertJsonCount(0, 'establecimientos')
            ->assertJsonPath('totalDirectorio', 4)
            ->assertJsonFragment(['valor' => (string) $this->lacteos->id, 'nombre' => 'Lácteos', 'cantidad' => 0]);

        $this->assertSame(['Mulaló' => 2], $this->conteos($respuesta, 'parroquia'));
        $this->assertSame([$this->bebidas->id => 1, $this->lacteos->id => 0], $this->conteos($respuesta, 'categoria_id'));
    }

    public function test_los_conteos_respetan_radio_ubicacion_y_publicacion_del_interes(): void
    {
        config(['app.publicar_interes_encuesta' => true]);

        $respuesta = $this->buscar(['q' => 'Mercado', 'ubicados' => 1, 'interes_declarado' => 0])
            ->assertJsonCount(1, 'establecimientos')
            ->assertJsonPath('establecimientos.0.interesDeclarado', false);

        $this->assertSame([0 => 1, 1 => 1], $this->conteos($respuesta, 'interes_declarado'));

        $respuesta = $this->buscar(['q' => 'Mercado', 'latitud' => -0.775, 'longitud' => -78.58, 'distancia_km' => 2])
            ->assertJsonCount(1, 'establecimientos')
            ->assertJsonPath('establecimientos.0.distanciaKm', 0);

        $this->assertSame(['Mulaló' => 1], $this->conteos($respuesta, 'parroquia'));
        $this->assertSame([0 => 1], $this->conteos($respuesta, 'interes_declarado'));

        $this->getJson('/api/establecimientos/buscar?q=Mercado')->assertOk()
            ->assertJsonCount(3, 'establecimientos')
            ->assertJsonMissingPath('facetas')
            ->assertJsonMissingPath('totalDirectorio');
    }

    public function test_variantes_de_una_parroquia_tienen_el_mismo_conteo_que_el_filtro(): void
    {
        $this->comercio('Mercado D', 'tienda', 'MULALO', 'por_confirmar', [], null, null);
        $facetas = $this->buscar(['q' => 'Mercado'])->assertJsonCount(2, 'facetas.parroquia');
        $mulalo = collect($facetas->json('facetas.parroquia'))->firstWhere('cantidad', 3);

        $this->assertNotNull($mulalo);
        $this->buscar(['q' => 'Mercado', 'parroquia' => $mulalo['valor']])->assertJsonCount(3, 'establecimientos');
    }

    private function comercio(string $nombre, string $tipo, string $parroquia, string $recepcion, array $categorias, ?bool $interes, ?float $latitud): void
    {
        $ficha = Establecimiento::query()->create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'parroquia' => $parroquia,
            'recepcion_proveedores' => $recepcion,
            'latitud' => $latitud,
            'longitud' => $latitud === null ? null : -78.58,
        ]);
        $ficha->categorias()->sync($categorias);
        $perfil = $ficha->perfilComercial()->make();
        $perfil->interes_encuesta = $interes;
        $perfil->save();
    }

    private function buscar(array $filtros): TestResponse
    {
        return $this->getJson('/api/establecimientos/buscar?'.http_build_query(['facetas' => 1, ...$filtros]))->assertOk();
    }

    private function conteos(TestResponse $respuesta, string $faceta): array
    {
        return collect($respuesta->json('facetas.'.$faceta))->pluck('cantidad', 'valor')->all();
    }
}
