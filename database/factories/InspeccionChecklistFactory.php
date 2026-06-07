<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspeccionChecklist>
 */
class InspeccionChecklistFactory extends Factory
{
    protected $model = InspeccionChecklist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'id_juez' => User::factory()->juez(),
            'peso_medido_g' => fake()->numberBetween(100, 900),
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => 'Pendiente',
            'observaciones' => null,
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn (array $a) => ['estado_aprobacion' => 'Aprobado']);
    }
}
