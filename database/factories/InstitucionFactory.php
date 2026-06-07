<?php

namespace Database\Factories;

use App\Models\Institucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institucion>
 */
class InstitucionFactory extends Factory
{
    protected $model = Institucion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'tipo' => fake()->randomElement(['Pública', 'Privada', 'Independiente']),
            'estado' => fake()->state(),
        ];
    }
}
