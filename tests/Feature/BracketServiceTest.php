<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\ParticipanteEncuentro;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaCombate(): Categoria
    {
        return Categoria::factory()->combate()->create();
    }

    /** Crea $n inscripciones aprobadas en la categoría. */
    private function aprobados(Categoria $categoria, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        }
    }

    public function test_genera_bracket_de_cuatro(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);

        (new BracketService)->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $this->assertCount(4, $encuentros); // 2 semifinales + final + tercer lugar

        $final = $encuentros->firstWhere('ronda', 'Final');
        $this->assertNull($final->id_encuentro_siguiente);

        $semis = $encuentros->where('ronda', 'Semifinal');
        $this->assertCount(2, $semis);
        $semis->each(fn ($s) => $this->assertSame($final->id_encuentro, $s->id_encuentro_siguiente));

        // 4 participantes repartidos en las 2 semifinales
        $this->assertSame(4, ParticipanteEncuentro::whereIn('id_encuentro', $semis->pluck('id_encuentro'))->count());
    }

    public function test_genera_bracket_de_ocho(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 8);

        (new BracketService)->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $this->assertCount(8, $encuentros); // 4 cuartos + 2 semis + final + tercer lugar
        $this->assertCount(4, $encuentros->where('ronda', 'Cuartos'));
        $this->assertCount(2, $encuentros->where('ronda', 'Semifinal'));
        $this->assertCount(1, $encuentros->where('ronda', 'Final'));
    }

    public function test_byes_se_autoavanzan_con_cinco(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 5); // size=8, rondas: Cuartos/Semifinal/Final, byes=3

        (new BracketService)->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $cuartos = $encuentros->where('ronda', 'Cuartos');
        $semis = $encuentros->where('ronda', 'Semifinal');

        // 3 byes: 3 ganadores marcados en Cuartos y 3 participantes ya en Semifinal
        $this->assertSame(3, ParticipanteEncuentro::whereIn('id_encuentro', $cuartos->pluck('id_encuentro'))->where('es_ganador', true)->count());
        $this->assertSame(3, ParticipanteEncuentro::whereIn('id_encuentro', $semis->pluck('id_encuentro'))->count());
    }

    public function test_minimo_dos_aprobados(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 1);

        $this->expectException(\DomainException::class);

        (new BracketService)->generar($categoria);
    }

    public function test_categoria_no_combate_lanza_excepcion(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();

        $this->expectException(\InvalidArgumentException::class);

        (new BracketService)->generar($categoria);
    }

    public function test_regenerar_borra_el_anterior(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);

        $service = new BracketService;
        $service->generar($categoria);
        $service->generar($categoria);

        $this->assertSame(4, Encuentro::where('id_categoria', $categoria->id_categoria)->count()); // 2 semis + final + tercer lugar
    }

    public function test_registrar_ganador_avanza_al_siguiente(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);
        $service = new BracketService;
        $service->generar($categoria);

        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        $service->registrarGanador($semi, $ganador);

        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro,
            'id_inscripcion' => $ganador,
            'es_ganador' => true,
        ]);
        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro_siguiente,
            'id_inscripcion' => $ganador,
        ]);
    }

    public function test_genera_bracket_minimo_de_dos(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 2);

        (new BracketService)->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $this->assertCount(1, $encuentros); // la Final es la única ronda
        $final = $encuentros->first();
        $this->assertSame('Final', $final->ronda);
        $this->assertNull($final->id_encuentro_siguiente);
        $this->assertSame(2, ParticipanteEncuentro::where('id_encuentro', $final->id_encuentro)->count());
        // sin byes: nadie marcado ganador todavía
        $this->assertSame(0, ParticipanteEncuentro::where('id_encuentro', $final->id_encuentro)->where('es_ganador', true)->count());
    }

    public function test_ganador_de_la_final_no_crea_siguiente(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 2);
        $service = new BracketService;
        $service->generar($categoria);

        $final = Encuentro::where('id_categoria', $categoria->id_categoria)->first();
        $ganador = $final->participantes()->first()->id_inscripcion;

        $service->registrarGanador($final, $ganador);

        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $final->id_encuentro,
            'id_inscripcion' => $ganador,
            'es_ganador' => true,
        ]);
        // No hay encuentro siguiente: el total de encuentros sigue en 1
        $this->assertSame(1, Encuentro::where('id_categoria', $categoria->id_categoria)->count());
    }
}
