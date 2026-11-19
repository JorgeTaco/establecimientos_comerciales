<?php

namespace App\Importacion;

final class CategoriasLacteas
{
    public const NOMBRES = ['Leche', 'Queso', 'Yogurt', 'Mantequilla'];

    public static function desdeProductos(string $productos): array
    {
        $categorias = [];

        foreach (preg_split('/[,;|\r\n]+/u', $productos) ?: [] as $producto) {
            $categoria = match (MapaColumnas::clave($producto)) {
                'leche', 'leches' => 'Leche',
                'queso', 'quesos' => 'Queso',
                'yogur', 'yogurt', 'yoghurt', 'yogures', 'yogurts' => 'Yogurt',
                'mantequilla', 'mantequillas' => 'Mantequilla',
                default => null,
            };

            if ($categoria !== null) {
                $categorias[] = $categoria;
            }
        }

        return array_values(array_unique($categorias));
    }
}
