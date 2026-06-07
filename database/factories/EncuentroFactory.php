<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Encuentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encuentro>
 */
class EncuentroFactory extends Factory
{
    protected $model = Encuentro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_categoria' => Categoria::factory()->combate(),
            'ronda' => fake()->randomElement(['Cuartos', 'Semifinal', 'Final']),
            'id_encuentro_siguiente' => null,
        ];
    }
}
