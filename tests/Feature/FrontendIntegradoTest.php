<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontendIntegradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cuenta_del_docente_entra_y_regresa_al_recorrido_de_evaluacion(): void
    {
        $usuario = User::factory()->create();

        $this->get('/evaluacion')->assertOk()->assertSee('Entrar con la cuenta de evaluación');
        $this->get('/iniciar-sesion?evaluacion=1')->assertOk()->assertSee('name="evaluacion" value="1"', false);

        $this->post('/iniciar-sesion', [
            'email' => $usuario->email,
            'password' => 'password',
            'evaluacion' => '1',
        ])->assertRedirectToRoute('evaluacion');
        $this->assertAuthenticatedAs($usuario);
        $this->get('/evaluacion')->assertOk()->assertSee('Registrar un producto');
        $this->get('/productos/crear')->assertOk();
    }

    public function test_emprendedor_guarda_y_actualiza_una_oportunidad_sin_exponerla_a_otros(): void
    {
        $usuario = User::factory()->create();
        $establecimiento = Establecimiento::query()->create(['nombre' => 'Tienda de prueba', 'tipo' => 'tienda', 'parroquia' => 'Mulaló']);

        $this->get('/oportunidades')->assertRedirectToRoute('login');
        $this->get('/registro')->assertOk()->assertSee('Confirmar contraseña');
        $this->get('/establecimientos/'.$establecimiento->id)->assertOk()->assertSee($establecimiento->nombre);

        $respuesta = $this->actingAs($usuario)->post('/oportunidades', ['establecimiento_id' => $establecimiento->id]);
        $oportunidad = $usuario->oportunidades()->sole();
        $respuesta->assertRedirectToRoute('oportunidades.show', $oportunidad);
        $this->get('/productos')->assertOk();
        $this->get('/oportunidades/'.$oportunidad->id)->assertOk()->assertSee('Guardar seguimiento');
        $this->patch('/oportunidades/'.$oportunidad->id, [
            'estado' => 'primer_pedido',
            'notas' => 'Nota privada del flujo.',
            'proxima_gestion' => '2026-12-01',
        ])->assertRedirectToRoute('oportunidades.show', $oportunidad);
        $this->getJson('/api/oportunidades/'.$oportunidad->id)->assertOk()
            ->assertJsonPath('oportunidad.establecimiento.nombre', $establecimiento->nombre)
            ->assertJsonPath('oportunidad.estado', 'primer_pedido')
            ->assertJsonPath('oportunidad.proxima_gestion', '2026-12-01')
            ->assertJsonCount(0, 'oportunidad.propuestas');
        $this->assertNotNull($oportunidad->refresh()->primer_pedido_en);

        $this->actingAs(User::factory()->create())->getJson('/api/oportunidades/'.$oportunidad->id)->assertNotFound();
        $this->get('/oportunidades/'.$oportunidad->id)->assertNotFound();
    }

    public function test_solo_el_comercio_responsable_abre_la_propuesta_y_puede_responder(): void
    {
        $emprendedor = User::factory()->create();
        $comercio = User::factory()->create(['rol' => 'comercio']);
        $establecimiento = Establecimiento::query()->create(['nombre' => 'Tienda de prueba', 'tipo' => 'tienda', 'parroquia' => 'Mulaló']);
        $establecimiento->perfilComercial()->create();
        $establecimiento->perfilComercial()->update(['user_id' => $comercio->id]);
        $oportunidad = $emprendedor->oportunidades()->create(['establecimiento_id' => $establecimiento->id, 'notas' => 'Privado']);
        $propuesta = $oportunidad->propuestas()->create(['mensaje' => 'Propuesta de prueba', 'productos' => []]);

        $this->actingAs($comercio)->get('/propuestas-recibidas')->assertOk();
        $this->get('/propuestas-recibidas/'.$propuesta->id.'/responder')->assertOk();
        $this->getJson('/api/propuestas-recibidas/'.$propuesta->id)->assertOk()
            ->assertJsonPath('propuesta.establecimiento_id', (string) $establecimiento->id)
            ->assertJsonMissingPath('propuesta.notas');
        $this->patchJson('/api/propuestas-recibidas/'.$propuesta->id.'/respuesta', ['respuesta' => 'me_interesa'])
            ->assertOk()->assertJsonPath('propuesta.respuesta', 'me_interesa');
        $this->get('/productos')->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => 'comercio']))->get('/propuestas-recibidas/'.$propuesta->id.'/responder')
            ->assertNotFound();
    }

    public function test_administrador_simula_e_importa_archivo_sin_exponer_contactos(): void
    {
        Storage::fake('local');
        $ruta = '/api/admin/establecimientos/importar-archivo';
        $contenido = "Nombre del establecimiento,Tipo de establecimiento,Parroquia,Interes en nuevos proveedores\nTienda nueva,Tienda,Mulalo,Si\n";
        $archivo = fn () => UploadedFile::fake()->createWithContent('encuesta.csv', $contenido);
        $headers = ['Accept' => 'application/json'];

        $this->actingAs(User::factory()->create())->post($ruta, ['archivo' => $archivo(), 'simular' => '1'], $headers)->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->get('/admin/indicadores')->assertOk()->assertSee('Simular carga');
        $this->post($ruta, ['archivo' => $archivo(), 'simular' => '1'], $headers)
            ->assertOk()->assertJsonPath('resumen.validas_para_importar', 1)->assertJsonPath('resumen.importadas', 0);
        $this->assertDatabaseCount('establecimientos', 0);

        $this->post($ruta, ['archivo' => $archivo(), 'simular' => '0'], $headers)
            ->assertOk()->assertJsonPath('resumen.importadas', 1)->assertJsonPath('filas.0.estado', 'importada')
            ->assertJsonMissingPath('filas.0.datos');
        $this->assertDatabaseHas('establecimientos', ['nombre' => 'Tienda nueva', 'recepcion_proveedores' => 'por_confirmar']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
