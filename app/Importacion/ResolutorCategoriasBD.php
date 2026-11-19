<?php

namespace App\Importacion;

use App\Models\Categoria;

class ResolutorCategoriasBD implements ResolutorCategorias
{
    public function resolver(array $nombres): array
    {
        $nombres = array_values(array_unique(array_filter(
            array_map(fn ($nombre) => trim((string) $nombre), $nombres),
            fn ($nombre) => $nombre !== ''
        )));

        if ($nombres === []) {
            return ['ids' => [], 'desconocidas' => []];
        }

        $porClave = [];
        foreach (Categoria::query()->where('activa', true)->get(['id', 'nombre']) as $categoria) {
            $porClave[MapaColumnas::clave($categoria->nombre)] = $categoria->id;
        }

        $ids = [];
        $desconocidas = [];
        foreach ($nombres as $nombre) {
            $clave = MapaColumnas::clave($nombre);
            if ($clave === '') {
                continue;
            }
            if (isset($porClave[$clave])) {
                $ids[] = (int) $porClave[$clave];

                continue;
            }
            $desconocidas[] = $nombre;
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'desconocidas' => $desconocidas,
        ];
    }
}
