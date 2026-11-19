<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CuentaActiva
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();
        abort_if($usuario->suspendido_en !== null, 403);
        abort_if($roles !== [] && ! in_array($usuario->rol, $roles, true), 403);

        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'private, no-store');

        return $respuesta;
    }
}
