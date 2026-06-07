<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Robot>
 */
class RobotFactory extends Factory
{
    protected $model = Robot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'id_piloto' => User::factory(),
            'id_institucion' => Institucion::factory(),
            'id_categoria' => Categoria::factory(),
        ];
    }
}
