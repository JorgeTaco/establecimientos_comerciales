<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_administrador_con_clave_encriptada(): void
    {
        $this->artisan('usuario:crear', [
            'email' => 'admin@example.test',
            '--nombre' => 'Administrador Demo',
            '--rol' => 'administrador',
            '--clave' => 'ClaveSegura123',
        ])->assertSuccessful();

        $usuario = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Administrador Demo', $usuario->name);
        $this->assertSame('administrador', $usuario->rol);
        $this->assertNull($usuario->suspendido_en);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertTrue(Hash::check('ClaveSegura123', $usuario->password));
    }

    public function test_actualiza_rol_y_clave_de_una_cuenta_existente(): void
    {
        User::factory()->create([
            'email' => 'comercio@example.test',
            'rol' => 'emprendedor',
        ]);

        $this->artisan('usuario:crear', [
            'email' => 'comercio@example.test',
            '--rol' => 'comercio',
            '--clave' => 'NuevaClave123',
        ])->assertSuccessful();

        $usuario = User::query()->where('email', 'comercio@example.test')->firstOrFail();

        $this->assertSame('comercio', $usuario->rol);
        $this->assertTrue(Hash::check('NuevaClave123', $usuario->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_rechaza_roles_y_claves_invalidas(): void
    {
        $this->artisan('usuario:crear', [
            'email' => 'invalido@example.test',
            '--rol' => 'superadministrador',
            '--clave' => 'ClaveSegura123',
        ])->assertFailed();

        $this->artisan('usuario:crear', [
            'email' => 'corta@example.test',
            '--rol' => 'emprendedor',
            '--clave' => 'corta',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'invalido@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'corta@example.test']);
    }
}
