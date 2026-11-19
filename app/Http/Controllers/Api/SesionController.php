<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SesionController
{
    public function csrf(): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    public function registrar(Request $request): JsonResponse
    {
        abort_if($request->user() !== null, 409, 'Ya hay una sesión iniciada.');
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), $this->limitePassword(...)],
            'rol' => ['required', Rule::in(['emprendedor', 'comercio'])],
        ]);

        $usuario = new User([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $usuario->rol = $datos['rol'];
        $usuario->save();

        Auth::login($usuario);
        $request->session()->regenerate();

        return $this->usuario($request, 201);
    }

    public function ingresar(Request $request): JsonResponse
    {
        abort_if($request->user() !== null, 409, 'Ya hay una sesión iniciada.');
        $credenciales = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', $this->limitePassword(...)],
        ]);

        if (! Auth::attempt($credenciales + ['suspendido_en' => null])) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son válidas.']);
        }

        $request->session()->regenerate();

        return $this->usuario($request);
    }

    public function usuario(Request $request, int $status = 200): JsonResponse
    {
        return response()->json([
            'usuario' => $request->user()->only(['id', 'name', 'email', 'rol']),
            'csrf_token' => csrf_token(),
        ], $status)->header('Cache-Control', 'no-store');
    }

    public function salir(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function limitePassword(string $atributo, mixed $valor, Closure $error): void
    {
        if (is_string($valor) && (strlen($valor) > 72 || str_contains($valor, "\0"))) {
            $error('La contraseña debe tener como máximo 72 bytes y no puede contener bytes nulos.');
        }
    }
}
