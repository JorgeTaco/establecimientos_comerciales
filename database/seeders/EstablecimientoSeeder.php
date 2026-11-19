<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Establecimiento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class EstablecimientoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = Categoria::query()->whereIn('nombre', ['Lácteos', 'Abarrotes'])->pluck('id')->all();

        $fichas = [
            [
                'nombre' => 'Tienda Demo Amanecer',
                'tipo' => 'tienda',
                'parroquia' => 'Mulaló',
                'latitud' => -0.775,
                'longitud' => -78.58,
                'recepcion_proveedores' => 'recibe_propuestas',
                'perfil' => [
                    'direccion' => 'Calle ficticia 123',
                    'canton' => 'Latacunga',
                    'sector' => 'Centro',
                    'productos_buscados' => ['Yogur', 'Leche'],
                    'tiene_refrigeracion' => true,
                    'horario_proveedores' => 'Lunes de 09:00 a 11:00',
                    'modalidad_compra' => 'contado',
                    'pedido_inicial' => 12,
                    'decision_compra' => 'sucursal',
                    'interes_encuesta' => true,
                ],
            ],
            [
                'nombre' => 'Minimercado Demo Los Pinos',
                'tipo' => 'minimercado',
                'parroquia' => 'Tanicuchí',
                'latitud' => -0.81,
                'longitud' => -78.635,
                'recepcion_proveedores' => 'no_recibe_por_ahora',
                'perfil' => [
                    'direccion' => 'Avenida ficticia 456',
                    'canton' => 'Latacunga',
                    'sector' => 'Norte',
                    'productos_buscados' => ['Queso'],
                    'tiene_refrigeracion' => true,
                    'horario_proveedores' => 'Martes de 10:00 a 12:00',
                    'modalidad_compra' => 'credito',
                    'pedido_inicial' => 6,
                    'decision_compra' => 'sucursal',
                    'interes_encuesta' => false,
                ],
            ],
            [
                'nombre' => 'Supermercado Demo Cotopaxi',
                'tipo' => 'supermercado',
                'parroquia' => 'Guaytacama',
                'latitud' => null,
                'longitud' => null,
                'recepcion_proveedores' => 'por_confirmar',
                'perfil' => [
                    'direccion' => 'Vía ficticia s/n',
                    'canton' => 'Latacunga',
                    'sector' => 'Rural',
                    'productos_buscados' => ['Yogur', 'Mantequilla'],
                    'tiene_refrigeracion' => null,
                    'horario_proveedores' => null,
                    'modalidad_compra' => null,
                    'pedido_inicial' => null,
                    'decision_compra' => null,
                    'interes_encuesta' => null,
                ],
            ],
        ];

        foreach ($fichas as $ficha) {
            $perfilDatos = $ficha['perfil'];
            unset($ficha['perfil']);

            $establecimiento = Establecimiento::query()->firstOrCreate(['nombre' => $ficha['nombre']], $ficha);
            $perfil = $establecimiento->perfilComercial()->firstOrNew([], Arr::except($perfilDatos, ['interes_encuesta']));
            $perfil->interes_encuesta = $perfilDatos['interes_encuesta'] ?? null;
            $perfil->save();

            if ($categorias !== []) {
                $establecimiento->categorias()->sync($categorias);
            }
        }
    }
}
