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
use App\Services\BracketService;
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

    /** @return array{0: Encuentro, 1: int, 2: int} [encuentro, idA, idB] */
    private function encuentroConDos(): array
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        // Encuentro de semifinal con un siguiente (final) para probar el avance.
        $final = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria, 'id_encuentro_siguiente' => $final->id_encuentro]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        return [$encuentro, $a->id_inscripcion, $b->id_inscripcion];
    }

    public function test_mejor_de_tres_decide_a_dos_rounds(): void
    {
        [$encuentro, $a, $b] = $this->encuentroConDos();
        $service = new BracketService;

        $service->registrarRound($encuentro, $a);
        $this->assertNull($encuentro->fresh()->tipo_resultado); // 1-0, sin ganador aún

        $service->registrarRound($encuentro, $a); // 2-0
        $encuentro->refresh();
        $this->assertSame('Rounds', $encuentro->tipo_resultado);
        $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'es_ganador' => true]);
        // avanzó al siguiente
        $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro_siguiente, 'id_inscripcion' => $a]);
    }

    public function test_round_repetido_no_cuenta(): void
    {
        [$encuentro, $a, $b] = $this->encuentroConDos();
        $service = new BracketService;

        $service->registrarRound($encuentro, $a);            // 1-0
        $service->registrarRound($encuentro, null, true);    // repetido, no cuenta
        $this->assertNull($encuentro->fresh()->tipo_resultado);
        $this->assertSame(2, $encuentro->fresh()->rounds()->count());
    }

    public function test_default_decide_y_avanza(): void
    {
        [$encuentro, $a, $b] = $this->encuentroConDos();
        (new BracketService)->ganarPorDefault($encuentro, $a);

        $encuentro->refresh();
        $this->assertSame('Default', $encuentro->tipo_resultado);
        $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'es_ganador' => true]);
        $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro_siguiente, 'id_inscripcion' => $a]);
    }

    public function test_descalificacion_da_el_encuentro_al_rival(): void
    {
        [$encuentro, $a, $b] = $this->encuentroConDos();
        (new BracketService)->descalificar($encuentro, $a); // descalifica A → gana B

        $encuentro->refresh();
        $this->assertSame('Descalificacion', $encuentro->tipo_resultado);
        $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b, 'es_ganador' => true]);
    }

    public function test_amonestar_registra_sin_alterar_resultado(): void
    {
        [$encuentro, $a, $b] = $this->encuentroConDos();
        $juez = User::factory()->juez()->create();

        (new BracketService)->amonestar($encuentro, $a, 'Colocó tarde', $juez->id, 1);

        $this->assertDatabaseHas('amonestaciones', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'motivo' => 'Colocó tarde']);
        $this->assertNull($encuentro->fresh()->tipo_resultado);
    }
}
