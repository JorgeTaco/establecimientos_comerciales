<?php

namespace Database\Factories;

use App\Models\Establecimiento;
use App\Models\PerfilComercial;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerfilComercialFactory extends Factory
{
    protected $model = PerfilComercial::class;

    public function definition(): array
    {
        return [
            'establecimiento_id' => Establecimiento::factory(),
            'foto_url' => null,
            'direccion' => fake()->streetAddress(),
            'canton' => 'Latacunga',
            'sector' => fake()->randomElement(['Centro', 'Norte', 'Sur', 'Rural']),
            'productos_buscados' => fake()->randomElements(['Yogur', 'Leche', 'Queso', 'Mantequilla'], fake()->numberBetween(1, 3)),
            'tiene_refrigeracion' => fake()->boolean(),
            'horario_proveedores' => 'Lunes a viernes de 09:00 a 11:00',
            'modalidad_compra' => fake()->randomElement(['contado', 'credito', 'consignacion']),
            'pedido_inicial' => fake()->numberBetween(6, 24),
            'decision_compra' => fake()->randomElement(['sucursal', 'oficina_central']),
        ];
    }
}
