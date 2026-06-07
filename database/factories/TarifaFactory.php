<?php

namespace Database\Factories;

use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarifa>
 */
class TarifaFactory extends Factory
{
    protected $model = Tarifa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->randomElement(['Preventa', 'Fase Regular', 'Tardía']),
            'fecha_inicio_cobro' => '2026-01-01',
            'fecha_fin_cobro' => '2026-12-31',
            'monto' => fake()->randomElement([150.00, 250.00, 400.00]),
        ];
    }
}
