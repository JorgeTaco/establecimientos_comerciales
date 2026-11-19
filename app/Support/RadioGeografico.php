<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class RadioGeografico
{
    public const RADIO_TERRESTRE_KM = 6371.0088;

    public static function limitarCandidatos(Builder $consulta, float $latitud, float $longitud, float $radio): void
    {
        // Las coordenadas se almacenan con seis decimales. El margen evita perder puntos en el borde.
        $angular = $radio / self::RADIO_TERRESTRE_KM;
        $margen = 0.000001;
        $latitudMinima = $latitud - rad2deg($angular) - $margen;
        $latitudMaxima = $latitud + rad2deg($angular) + $margen;
        $consulta->whereBetween('latitud', [max(-90, $latitudMinima), min(90, $latitudMaxima)]);

        if ($latitudMinima <= -90 || $latitudMaxima >= 90) {
            return;
        }

        $ancho = rad2deg(asin(min(1, sin($angular) / cos(deg2rad($latitud))))) + $margen;
        $oeste = $longitud - $ancho;
        $este = $longitud + $ancho;
        if ($oeste < -180) {
            $consulta->where(fn ($q) => $q->where('longitud', '>=', $oeste + 360)->orWhere('longitud', '<=', $este));
        } elseif ($este > 180) {
            $consulta->where(fn ($q) => $q->where('longitud', '>=', $oeste)->orWhere('longitud', '<=', $este - 360));
        } else {
            $consulta->whereBetween('longitud', [$oeste, $este]);
        }
    }
}
