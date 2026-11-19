<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\LimitePassword;
use App\Support\FlujoComercial;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CrearUsuario extends Command
{
    protected $signature = 'usuario:crear
        {email : Correo de la cuenta}
        {--nombre= : Nombre visible}
        {--rol= : Rol de una cuenta nueva (emprendedor por defecto)}
        {--actualizar-clave : Cambia solo la contraseña de una cuenta existente}';

    protected $description = 'Crea una cuenta con rol o cambia su contraseña de forma explícita.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $rol = Str::lower(trim($this->option('rol') ?? 'emprendedor'));
        $nombre = trim((string) $this->option('nombre')) ?: Str::headline(Str::before($email, '@'));
        $actualizar = $this->option('actualizar-clave');

        if ($actualizar && ($this->option('rol') !== null || $this->option('nombre') !== null)) {
            $this->error('El cambio de contraseña solo admite el correo de la cuenta.');

            return self::FAILURE;
        }

        if (! $this->validarDatos([
            'email' => $email,
            'nombre' => $nombre,
            'rol' => $rol,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'rol' => ['required', Rule::in(FlujoComercial::ROLES)],
        ])) {
            return self::FAILURE;
        }

        $usuario = User::query()->where('email', $email)->first();
        if ($usuario !== null && ! $actualizar) {
            $this->error('Ya existe una cuenta con ese correo. Usa --actualizar-clave para cambiar su contraseña.');

            return self::FAILURE;
        }
        if ($usuario === null && $actualizar) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta el comando en una terminal interactiva para introducir la contraseña.');

            return self::FAILURE;
        }

        $clave = $this->secret('Contraseña (mínimo 12 caracteres)', false);
        $confirmacion = $this->secret('Repite la contraseña', false);
        if (! $this->validarDatos(['password' => $clave, 'password_confirmation' => $confirmacion], [
            'password' => ['required', 'string', 'confirmed', Password::min(12), new LimitePassword],
        ])) {
            return self::FAILURE;
        }

        if ($usuario !== null) {
            $usuario->password = $clave;
            $usuario->save();
            $this->info("Contraseña actualizada para {$email}.");

            return self::SUCCESS;
        }

        $usuario = new User(['name' => $nombre, 'email' => $email, 'password' => $clave]);
        $usuario->rol = $rol;
        try {
            $usuario->save();
        } catch (UniqueConstraintViolationException) {
            $this->error('Ya existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $this->info("Cuenta creada: {$email} ({$rol})");

        return self::SUCCESS;
    }

    private function validarDatos(array $datos, array $reglas): bool
    {
        $errores = Validator::make($datos, $reglas)->errors()->all();
        foreach ($errores as $error) {
            $this->error($error);
        }

        return $errores === [];
    }
}
