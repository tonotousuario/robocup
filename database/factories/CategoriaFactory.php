<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Mini Sumo', 'Guerra', 'Seguidor de Línea', 'Laberinto']),
            'tipo_evaluacion' => fake()->randomElement(['Combate', 'Tiempo']),
            'peso_maximo_g' => fake()->randomElement([500, 1000, 30000]),
            'dimensiones_maximas' => '20x20 cm',
        ];
    }

    public function tiempo(): static
    {
        return $this->state(fn (array $a) => ['tipo_evaluacion' => 'Tiempo']);
    }

    public function combate(): static
    {
        return $this->state(fn (array $a) => ['tipo_evaluacion' => 'Combate']);
    }
}
