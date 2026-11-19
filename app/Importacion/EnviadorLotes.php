<?php

namespace App\Importacion;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EnviadorLotes
{
    public function enviar(array $lotes, string $url, string $correo, string $clave): array
    {
        if ($correo === '' || $clave === '') {
            throw new RuntimeException('Indica el correo y la clave de un administrador con --correo y --clave.');
        }

        $cliente = Http::withOptions(['cookies' => new CookieJar])
            ->baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->asJson();

        $sesion = $cliente->post('/api/sesion', ['email' => $correo, 'password' => $clave]);
        if (! $sesion->successful()) {
            throw new RuntimeException("No se pudo iniciar sesión en la API (HTTP {$sesion->status()}).");
        }

        $token = $sesion->json('csrf_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('La API no devolvió un token CSRF.');
        }

        $respuestas = [];
        foreach ($lotes as $indice => $lote) {
            $respuesta = $cliente->withHeader('X-CSRF-TOKEN', $token)
                ->post('/api/admin/establecimientos/importar', ['filas' => $lote['filas']]);

            $resultados = $respuesta->json('resultados', []);
            $respuestas[] = [
                'lote' => $indice + 1,
                'estado_http' => $respuesta->status(),
                'resultados' => is_array($resultados) ? $resultados : [],
            ];
        }

        return $respuestas;
    }
}
