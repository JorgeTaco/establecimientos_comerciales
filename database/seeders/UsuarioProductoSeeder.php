<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Oportunidad;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsuarioProductoSeeder extends Seeder
{
    public function run(): void
    {
        // Emprendedor (oferta productos)
        $emprendedor = User::query()->firstOrCreate(
            ['email' => 'emprendedor@ejemplo.test'],
            [
                'name' => 'Emprendedor Demo',
                'password' => bcrypt('demo12345678'),
                'rol' => 'emprendedor',
            ]
        );

        // Comercio (recibe propuestas)
        $comercio = User::query()->firstOrCreate(
            ['email' => 'comercio@ejemplo.test'],
            [
                'name' => 'Comercio Demo',
                'password' => bcrypt('demo12345678'),
                'rol' => 'comercio',
            ]
        );

        // Administrador
        User::query()->firstOrCreate(
            ['email' => 'admin@ejemplo.test'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('demo12345678'),
                'rol' => 'administrador',
            ]
        );

        $categoriaLacteos = Categoria::query()->where('nombre', 'Lácteos')->first();
        $categoriaAbarrotes = Categoria::query()->where('nombre', 'Abarrotes')->first();

        $productos = [
            [
                'nombre' => 'Yogur natural',
                'descripcion' => 'Yogur natural sin azúcar, envase de 500 g.',
                'categoria_id' => $categoriaLacteos?->id,
                'precio_comercio' => 0.80,
                'precio_sugerido' => 1.20,
                'pedido_minimo' => 24,
                'capacidad_semanal' => 200,
                'vida_util_dias' => 21,
                'requiere_refrigeracion' => true,
                'conservacion' => 'Mantener refrigerado entre 2 °C y 6 °C.',
                'zonas_entrega' => ['Mulaló', 'Tanicuchí', 'Guaytacama'],
                'frecuencia_entrega' => 'Semanal',
            ],
            [
                'nombre' => 'Queso fresco',
                'descripcion' => 'Queso fresco blanco, unidad de 300 g.',
                'categoria_id' => $categoriaLacteos?->id,
                'precio_comercio' => 1.10,
                'precio_sugerido' => 1.70,
                'pedido_minimo' => 12,
                'capacidad_semanal' => 100,
                'vida_util_dias' => 14,
                'requiere_refrigeracion' => true,
                'conservacion' => 'Mantener refrigerado.',
                'zonas_entrega' => ['Mulaló', 'Tanicuchí'],
                'frecuencia_entrega' => 'Semanal',
            ],
            [
                'nombre' => 'Leche entera',
                'descripcion' => 'Leche entera pasteurizada, bolsa de 1 litro.',
                'categoria_id' => $categoriaLacteos?->id,
                'precio_comercio' => 0.90,
                'precio_sugerido' => 1.30,
                'pedido_minimo' => 30,
                'capacidad_semanal' => 300,
                'vida_util_dias' => 10,
                'requiere_refrigeracion' => true,
                'conservacion' => 'Mantener refrigerado.',
                'zonas_entrega' => ['Guaytacama', 'Lasso', 'Salache'],
                'frecuencia_entrega' => 'Dos veces por semana',
            ],
            [
                'nombre' => 'Arroz',
                'descripcion' => 'Arroz blanco grano largo, paquete de 1 kg.',
                'categoria_id' => $categoriaAbarrotes?->id,
                'precio_comercio' => 0.60,
                'precio_sugerido' => 0.95,
                'pedido_minimo' => 20,
                'capacidad_semanal' => 500,
                'vida_util_dias' => 365,
                'requiere_refrigeracion' => false,
                'conservacion' => 'Lugar fresco y seco.',
                'zonas_entrega' => ['Mulaló', 'Tanicuchí', 'Guaytacama', 'Lasso', 'Salache'],
                'frecuencia_entrega' => 'Semanal',
            ],
        ];

        foreach ($productos as $datos) {
            $datos['user_id'] = $emprendedor->id;
            Producto::query()->firstOrCreate(
                ['nombre' => $datos['nombre'], 'user_id' => $emprendedor->id],
                $datos
            );
        }

        // Crear una oportunidad de ejemplo vinculada al primer establecimiento con ficha
        $establecimiento = \App\Models\Establecimiento::query()->first();
        if ($establecimiento && $emprendedor) {
            Oportunidad::query()->firstOrCreate(
                ['user_id' => $emprendedor->id, 'establecimiento_id' => $establecimiento->id],
                [
                    'estado' => 'por_contactar',
                    'notas' => 'Primera toma de contacto para ofrecer lácteos.',
                ]
            );
        }
    }
}
