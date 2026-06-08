<?php

namespace Tests\Feature;

use App\Models\Amonestacion;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\ParticipanteEncuentro;
use App\Models\Robot;
use App\Models\RoundEncuentro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombateRoundsTest extends TestCase
{
    use RefreshDatabase;

    /** Crea una inscripción aprobada en una categoría de combate. */
    private function inscripcionAprobada(Categoria $categoria): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    public function test_tablas_y_columnas_existen(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        $round = RoundEncuentro::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'numero_round' => 1,
            'id_inscripcion_ganador' => $a->id_inscripcion,
            'repetido' => false,
        ]);
        $this->assertDatabaseHas('rounds_encuentro', ['id_round' => $round->id_round, 'numero_round' => 1]);

        $juez = User::factory()->juez()->create();
        $amon = Amonestacion::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'id_inscripcion' => $b->id_inscripcion,
            'id_juez' => $juez->id,
            'numero_round' => 1,
            'motivo' => 'Tocó el robot en el dohyo',
        ]);
        $this->assertDatabaseHas('amonestaciones', ['id_amonestacion' => $amon->id_amonestacion, 'motivo' => 'Tocó el robot en el dohyo']);

        $encuentro->update(['tipo_resultado' => 'Rounds']);
        $this->assertDatabaseHas('encuentros', ['id_encuentro' => $encuentro->id_encuentro, 'tipo_resultado' => 'Rounds']);

        $this->assertFalse($a->fresh()->reparacion_usada);
        $a->update(['reparacion_usada' => true]);
        $this->assertTrue($a->fresh()->reparacion_usada);
    }
}
