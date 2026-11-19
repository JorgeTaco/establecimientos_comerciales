<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('usuario:administrador {email}', function () {
    $usuario = User::query()->where('email', $this->argument('email'))->first();
    if ($usuario === null || $usuario->suspendido_en !== null) {
        $this->error('Registra una cuenta activa antes de asignarle el rol de administrador.');

        return 1;
    }

    $usuario->rol = 'administrador';
    $usuario->save();
    $this->info('Rol de administrador asignado.');

    return 0;
})->purpose('Asigna el rol de administrador a una cuenta existente');
