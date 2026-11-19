<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_administrador_con_contrasena_guardada_como_hash(): void
    {
        $this->artisan('usuario:crear', [
            'email' => 'admin@example.test',
            '--nombre' => 'Administrador Demo',
            '--rol' => 'administrador',
        ])->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveSegura123')
            ->expectsQuestion('Repite la contraseña', 'ClaveSegura123')
            ->assertSuccessful();

        $usuario = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Administrador Demo', $usuario->name);
        $this->assertSame('administrador', $usuario->rol);
        $this->assertNull($usuario->suspendido_en);
        $this->assertNull($usuario->email_verified_at);
        $this->assertTrue(Hash::check('ClaveSegura123', $usuario->password));
    }

    public function test_crear_rechaza_un_correo_existente_y_conserva_el_usuario(): void
    {
        $usuario = User::factory()->create([
            'email' => 'comercio@example.test',
            'rol' => 'emprendedor',
        ]);
        $datos = $usuario->refresh()->getAttributes();

        $this->artisan('usuario:crear', [
            'email' => 'comercio@example.test',
            '--rol' => 'administrador',
        ])->assertFailed();

        $this->assertSame($datos, $usuario->refresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_el_cambio_explicito_solo_actualiza_la_contrasena(): void
    {
        $usuario = User::factory()->create([
            'email' => 'comercio@example.test',
            'rol' => 'comercio',
            'suspendido_en' => now(),
        ]);
        $datos = $usuario->refresh()->only(['name', 'rol', 'email_verified_at', 'suspendido_en']);

        $this->artisan('usuario:crear', [
            'email' => $usuario->email,
            '--actualizar-clave' => true,
            '--rol' => 'administrador',
        ])->assertFailed();

        $this->artisan('usuario:crear', [
            'email' => $usuario->email,
            '--actualizar-clave' => true,
        ])->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'NuevaClave123')
            ->expectsQuestion('Repite la contraseña', 'NuevaClave123')
            ->assertSuccessful();

        $this->assertEquals($datos, $usuario->refresh()->only(array_keys($datos)));
        $this->assertTrue(Hash::check('NuevaClave123', $usuario->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_rechaza_roles_y_contrasenas_invalidas(): void
    {
        $this->artisan('usuario:crear', [
            'email' => 'invalido@example.test',
            '--rol' => 'superadministrador',
        ])->assertFailed();

        $this->artisan('usuario:crear', [
            'email' => 'corta@example.test',
            '--rol' => 'emprendedor',
        ])->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->assertFailed();

        $claveExtensa = str_repeat('á', 37);
        $this->artisan('usuario:crear', ['email' => 'larga@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', $claveExtensa)
            ->expectsQuestion('Repite la contraseña', $claveExtensa)
            ->assertFailed();

        $this->artisan('usuario:crear', ['email' => 'sin-confirmar@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveSegura123')
            ->expectsQuestion('Repite la contraseña', 'OtraClaveSegura123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
