<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Lácteos', 'Bebidas', 'Panadería', 'Abarrotes', 'Frutas y verduras'] as $nombre) {
            Categoria::query()->firstOrCreate(['nombre' => $nombre], ['activa' => true]);
        }
    }
}
