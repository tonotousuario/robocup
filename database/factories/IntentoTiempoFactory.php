<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\IntentoTiempo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntentoTiempo>
 */
class IntentoTiempoFactory extends Factory
{
    protected $model = IntentoTiempo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'numero_vuelta' => 1,
            'tiempo_logrado' => fake()->randomFloat(3, 5, 60),
            'penalizacion_segundos' => 0.000,
        ];
    }
}
