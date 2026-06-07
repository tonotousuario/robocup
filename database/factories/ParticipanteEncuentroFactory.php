<?php

namespace Database\Factories;

use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParticipanteEncuentro>
 */
class ParticipanteEncuentroFactory extends Factory
{
    protected $model = ParticipanteEncuentro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_encuentro' => Encuentro::factory(),
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'puntos_obtenidos' => 0,
            'es_ganador' => false,
        ];
    }
}
