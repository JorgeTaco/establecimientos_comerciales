<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_volver_al_directorio_conserva_los_filtros_del_mismo_sitio(): void
    {
        $establecimiento = Establecimiento::query()->create(['nombre' => 'Tienda de prueba', 'tipo' => 'tienda', 'parroquia' => 'Mulaló']);
        $ficha = route('establecimientos.ficha', $establecimiento);
        $directorio = route('establecimientos.index');
        $anterior = route('inicio', ['q' => 'Tienda', 'vista' => 'mapa', 'latitud' => '-0.775', 'ignorar' => 'dato']);
        $destino = route('establecimientos.index', ['q' => 'Tienda', 'vista' => 'mapa']);

        $this->get($ficha, ['Referer' => $anterior])->assertOk()
            ->assertSee('href="'.e($destino).'" aria-label="Volver · Directorio"', false)
            ->assertDontSee('latitud=-0.775', false)
            ->assertDontSee('ignorar=dato', false);
        $this->get($ficha, ['Referer' => 'https://otro.example/establecimientos?q=cambiar'])->assertOk()
            ->assertSee('href="'.$directorio.'" aria-label="Volver · Directorio"', false)
            ->assertDontSee('otro.example');
    }

    public function test_volver_a_compatibles_respeta_la_propiedad_del_producto(): void
    {
        $usuario = User::factory()->create();
        $categoria = Categoria::query()->create(['nombre' => 'Lácteos', 'activa' => true]);
        $producto = $usuario->productos()->create(['nombre' => 'Yogur de prueba', 'categoria_id' => $categoria->id]);
        $establecimiento = Establecimiento::query()->create(['nombre' => 'Tienda de prueba', 'tipo' => 'tienda', 'parroquia' => 'Mulaló']);
        $ficha = route('establecimientos.ficha', ['establecimiento' => $establecimiento, 'desde_producto' => $producto->id]);

        $this->actingAs($usuario)->get($ficha)->assertOk()
            ->assertSee('href="'.route('productos.compatibles', $producto).'" aria-label="Volver · Comercios compatibles"', false);
        $this->get(route('productos.edit', $producto))->assertOk()
            ->assertSee('href="'.route('productos.show', $producto).'" aria-label="Volver · Yogur de prueba"', false);
        $this->actingAs(User::factory()->create())->get($ficha)->assertOk()
            ->assertDontSee('Volver · Comercios compatibles')
            ->assertDontSee('Yogur de prueba');
    }

    public function test_el_menu_marca_la_seccion_y_muestra_los_accesos_del_rol(): void
    {
        $this->get('/')->assertOk()->assertSee('aria-current="page" >Directorio', false)->assertDontSee('Panel administrativo');
        $comercio = User::factory()->create(['rol' => 'comercio']);

        $this->actingAs($comercio)->get(route('propuestas.recibidas'))->assertOk()
            ->assertSee('aria-current="page" >Propuestas recibidas', false)
            ->assertDontSee('Mis oportunidades');
        $comercio->forceFill(['suspendido_en' => now()])->save();
        $this->actingAs($comercio->refresh())->get('/')->assertOk()
            ->assertDontSee('Mis establecimientos')->assertSee('Cerrar sesión');
    }
}
