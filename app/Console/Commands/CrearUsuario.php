<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrearUsuario extends Command
{
    protected $signature = 'usuario:crear
        {email : Correo de la cuenta}
        {--nombre= : Nombre visible}
        {--rol=emprendedor : emprendedor, comercio o administrador}
        {--clave= : Contraseña de al menos 12 caracteres}';

    protected $description = 'Crea o actualiza una cuenta local con rol para probar el sistema.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $rol = Str::lower(trim((string) $this->option('rol')));
        $nombre = trim((string) $this->option('nombre')) ?: Str::headline(Str::before($email, '@'));
        $clave = (string) $this->option('clave');

        if ($clave === '') {
            $clave = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
            $confirmacion = (string) $this->secret('Repite la contraseña');

            if ($clave !== $confirmacion) {
                $this->error('Las contraseñas no coinciden.');

                return self::FAILURE;
            }
        }

        $validador = Validator::make([
            'email' => $email,
            'nombre' => $nombre,
            'rol' => $rol,
            'clave' => $clave,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'rol' => ['required', Rule::in(['emprendedor', 'comercio', 'administrador'])],
            'clave' => ['required', 'string', 'min:12', 'max:72'],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::query()->firstOrNew(['email' => $email]);
        $usuario->name = $nombre;
        $usuario->password = Hash::make($clave);
        $usuario->rol = $rol;
        $usuario->suspendido_en = null;
        $usuario->email_verified_at ??= now();
        $usuario->save();

        $estado = $usuario->wasRecentlyCreated ? 'creada' : 'actualizada';
        $this->info("Cuenta {$estado}: {$email} ({$rol})");

        return self::SUCCESS;
    }
}
