<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;

final class CursorPaginacion
{
    public static function leer(Request $request, bool $porNombre = false): ?Cursor
    {
        $valor = $request->validate(['cursor' => ['nullable', 'string', 'max:2048']])['cursor'] ?? null;
        if ($valor === null) {
            return null;
        }

        $datos = json_decode(base64_decode(strtr($valor, '-_', '+/'), true) ?: '', true);
        if (! is_array($datos) || ! is_int($datos['id'] ?? null) || $datos['id'] < 1
            || ! is_bool($datos['_pointsToNextItems'] ?? null)
            || ($porNombre && ! is_string($datos['nombre'] ?? null))) {
            throw ValidationException::withMessages(['cursor' => 'El enlace de paginación no es válido.']);
        }

        return Cursor::fromEncoded($valor);
    }
}
