<?php

namespace Tests\Feature\Database;

use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\Robot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrar_institucion_pone_null_en_robot(): void
    {
        $institucion = Institucion::factory()->create();
        $robot = Robot::factory()->create(['id_institucion' => $institucion->id_institucion]);

        $institucion->delete();

        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'id_institucion' => null]);
    }

    public function test_borrar_robot_cascada_inscripciones(): void
    {
        $robot = Robot::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot]);

        $robot->delete();

        $this->assertDatabaseMissing('inscripciones', ['id_inscripcion' => $inscripcion->id_inscripcion]);
    }
}
