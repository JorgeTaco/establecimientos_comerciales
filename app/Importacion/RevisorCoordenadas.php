<?php

namespace App\Importacion;

/**
 * Revisa coordenadas antes de importar. No todas las observaciones bloquean
 * la carga: las coordenadas ausentes son válidas, pero las incompletas o
 * sospechosas se marcan para revisión manual.
 */
class RevisorCoordenadas
{
    public const OK = 'ok';

    public const SIN_COORDENADAS = 'sin_coordenadas';

    public const INCOMPLETAS = 'incompletas';

    public const FUERA_DE_RANGO = 'fuera_de_rango';

    public const POSIBLE_SIGNO = 'posible_signo';

    public const FUERA_DE_ZONA = 'fuera_de_zona';

    /**
     * @return array{estado: string, requiere_revision: bool, mensaje: ?string}
     */
    public function revisar(?float $latitud, ?float $longitud): array
    {
        if ($latitud === null && $longitud === null) {
            return [
                'estado' => self::SIN_COORDENADAS,
                'requiere_revision' => false,
                'mensaje' => 'Sin coordenadas; el comercio queda pendiente de ubicar en el mapa.',
            ];
        }

        if ($latitud === null || $longitud === null) {
            return [
                'estado' => self::INCOMPLETAS,
                'requiere_revision' => true,
                'mensaje' => 'Falta una de las dos coordenadas; deben venir ambas o ninguna.',
            ];
        }

        if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
            return [
                'estado' => self::FUERA_DE_RANGO,
                'requiere_revision' => true,
                'mensaje' => "Coordenadas fuera de rango: {$latitud}, {$longitud}.",
            ];
        }

        // Ecuador está en el hemisferio sur. Una latitud positiva con longitud
        // negativa es casi siempre un signo invertido en la encuesta.
        if ($latitud > 0 && $longitud < 0 && $latitud <= 1.5) {
            return [
                'estado' => self::POSIBLE_SIGNO,
                'requiere_revision' => true,
                'mensaje' => "Latitud positiva ({$latitud}); posible signo invertido. Puedes corregirla con --corregir-signo.",
            ];
        }

        // Caja amplia de Ecuador continental. Si cae fuera, probablemente la
        // coordenada está mal copiada.
        if ($latitud < -5.1 || $latitud > 1.7 || $longitud < -81.1 || $longitud > -75.1) {
            return [
                'estado' => self::FUERA_DE_ZONA,
                'requiere_revision' => true,
                'mensaje' => "Coordenadas fuera de Ecuador continental: {$latitud}, {$longitud}.",
            ];
        }

        return [
            'estado' => self::OK,
            'requiere_revision' => false,
            'mensaje' => null,
        ];
    }

    public function corregirSigno(?float $latitud): ?float
    {
        return $latitud === null ? null : -abs($latitud);
    }
}
