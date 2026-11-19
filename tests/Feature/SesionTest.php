<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SesionTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_consulta_el_contexto_de_sesion_con_y_sin_cuenta(): void
    {
        $this->getJson('/api/sesion/contexto')->assertOk()
            ->assertJsonPath('usuario', null)
            ->assertJsonPath('roles_registro', ['emprendedor', 'comercio'])
            ->assertJsonPath('destino', '/iniciar-sesion')
            ->assertJsonStructure(['csrf_token']);
        $this->getJson('/api/sesion')->assertUnauthorized();

        $usuario = User::factory()->create(['rol' => 'comercio']);
        $contexto = $this->actingAs($usuario)->getJson('/api/sesion/contexto')->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.rol', 'comercio')
            ->assertJsonPath('destino', '/establecimientos')
            ->assertJsonMissingPath('usuario.password');
        $this->assertStringContainsString('no-store', $contexto->headers->get('Cache-Control'));

        $usuario->forceFill(['suspendido_en' => now()])->save();
        $this->actingAs($usuario->refresh())->getJson('/api/sesion/contexto')->assertForbidden()
            ->assertJsonMissingPath('usuario');
    }

    public function test_una_cuenta_existente_puede_entrar_y_salir_desde_el_formulario_web(): void
    {
        $clave = 'clave-ficticia-web-123';
        $usuario = User::factory()->create(['password' => $clave]);
        $this->get('/establecimientos')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/iniciar-sesion')->assertOk()->assertSee('name="_token"', false);
        $this->post('/cerrar-sesion')->assertRedirectToRoute('login');

        $this->from('/iniciar-sesion')->post('/iniciar-sesion', [
            'email' => $usuario->email,
            'password' => 'clave-equivocada',
        ])->assertRedirectToRoute('login')->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();

        $this->post('/iniciar-sesion', ['email' => $usuario->email, 'password' => $clave])
            ->assertRedirectToRoute('establecimientos.index');
        $this->assertAuthenticatedAs($usuario);
        $this->get('/iniciar-sesion')->assertRedirectToRoute('establecimientos.index');
        $this->get('/establecimientos')->assertOk()->assertSee('Cerrar sesión');
        $this->getJson('/api/sesion')->assertOk()->assertJsonPath('usuario.email', $usuario->email);

        $this->post('/cerrar-sesion')->assertRedirectToRoute('login');
        Auth::forgetGuards();
        $this->getJson('/api/sesion')->assertUnauthorized();
    }

    public function test_el_limite_de_intentos_de_login_es_compartido_entre_web_y_api(): void
    {
        $datos = ['email' => 'docente@example.test', 'password' => 'clave-invalida'];
        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson('/api/sesion', $datos)->assertUnprocessable();
            $this->from('/iniciar-sesion')->post('/iniciar-sesion', $datos)->assertRedirectToRoute('login');
        }

        $this->postJson('/api/sesion', $datos)->assertTooManyRequests();
        $this->post('/iniciar-sesion', $datos)->assertTooManyRequests();
        $this->getJson('/api/sesion/contexto')->assertOk();
    }
}
