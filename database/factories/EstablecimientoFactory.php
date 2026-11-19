<?php

namespace Database\Factories;

use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class EstablecimientoFactory extends Factory
{
    protected $model = Establecimiento::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company().' '.fake()->randomElement(['Tienda', 'Minimercado', 'Supermercado']),
            'tipo' => fake()->randomElement(['tienda', 'minimercado', 'supermercado']),
            'parroquia' => fake()->randomElement(['Mulaló', 'Tanicuchí', 'Guaytacama', 'Centro', 'Toacazo']),
            'latitud' => round(fake()->latitude(-0.95, -0.65), 6),
            'longitud' => round(fake()->longitude(-78.75, -78.35), 6),
            'recepcion_proveedores' => fake()->randomElement(['recibe_propuestas', 'no_recibe_por_ahora', 'por_confirmar']),
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitud' => null,
            'longitud' => null,
        ]);
    }

    public function recibePropuestas(): static
    {
        return $this->state(fn (array $attributes) => [
            'recepcion_proveedores' => 'recibe_propuestas',
        ]);
    }

    public function porConfirmar(): static
    {
        return $this->state(fn (array $attributes) => [
            'recepcion_proveedores' => 'por_confirmar',
        ]);
    }
}
