<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SesionController
{
    private const ROLES_REGISTRO = ['emprendedor', 'comercio'];

    public function csrf(): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    public function contexto(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_if($usuario?->suspendido_en !== null, 403);

        return response()->json($this->datosSesion($request) + [
            'roles_registro' => self::ROLES_REGISTRO,
            'destino' => route($usuario === null ? 'login' : 'establecimientos.index', absolute: false),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function registrar(Request $request): JsonResponse
    {
        abort_if($request->user() !== null, 409, 'Ya hay una sesión iniciada.');
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), $this->limitePassword(...)],
            'rol' => ['required', Rule::in(self::ROLES_REGISTRO)],
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

    public function ingresar(Request $request): JsonResponse|RedirectResponse
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

        if ($request->routeIs('login.store')) {
            return to_route('establecimientos.index');
        }

        return $this->usuario($request);
    }

    public function usuario(Request $request, int $status = 200): JsonResponse
    {
        return response()->json($this->datosSesion($request), $status)->header('Cache-Control', 'no-store');
    }

    public function salir(Request $request): Response|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->routeIs('logout')) {
            return to_route('login');
        }

        return response()->noContent();
    }

    private function datosSesion(Request $request): array
    {
        return [
            'usuario' => $request->user()?->only(['id', 'name', 'email', 'rol']),
            'csrf_token' => $request->session()->token(),
        ];
    }

    private function limitePassword(string $atributo, mixed $valor, Closure $error): void
    {
        if (is_string($valor) && (strlen($valor) > 72 || str_contains($valor, "\0"))) {
            $error('La contraseña debe tener como máximo 72 bytes y no puede contener bytes nulos.');
        }
    }
}
