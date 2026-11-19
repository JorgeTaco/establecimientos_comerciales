<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class CacheComercial
{
    public const CATEGORIAS = 'comercial:categorias-activas:v1';

    public const INDICADORES = 'comercial:indicadores:v1';

    public static function clavePortada(bool $publicarInteres): string
    {
        return 'comercial:directorio:v1:'.(int) $publicarInteres;
    }

    public static function invalidarDirectorio(): void
    {
        Cache::forget(self::clavePortada(false));
        Cache::forget(self::clavePortada(true));
    }
}
