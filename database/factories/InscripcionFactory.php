<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\Robot;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_robot' => Robot::factory(),
            'id_tarifa' => Tarifa::factory(),
            'monto_pagado' => 0.00,
            'estado_pago' => 'Pendiente',
        ];
    }

    public function pagada(): static
    {
        return $this->state(fn (array $a) => ['estado_pago' => 'Pagado', 'monto_pagado' => 250.00]);
    }
}
