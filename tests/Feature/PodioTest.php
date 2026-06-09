<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PodioTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaConAprobados(int $n): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }

        return $categoria;
    }

    public function test_generar_crea_match_de_tercer_lugar_con_semifinales(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);

        $this->assertDatabaseHas('encuentros', [
            'id_categoria' => $categoria->id_categoria,
            'ronda' => 'Tercer lugar',
        ]);
    }

    public function test_generar_no_crea_tercer_lugar_con_dos_robots(): void
    {
        $categoria = $this->categoriaConAprobados(2);
        (new BracketService)->generar($categoria);

        $this->assertDatabaseMissing('encuentros', [
            'id_categoria' => $categoria->id_categoria,
            'ronda' => 'Tercer lugar',
        ]);
    }

    public function test_perdedores_de_semifinal_van_al_tercer_lugar(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        $service = new BracketService;
        $service->generar($categoria);

        $semis = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->get();
        $this->assertCount(2, $semis);

        // Decidir cada semifinal por su primer participante.
        $perdedores = [];
        foreach ($semis as $semi) {
            $ids = $semi->participantes()->pluck('id_inscripcion')->all();
            $ganador = $ids[0];
            $perdedores[] = $ids[1];
            $service->registrarGanador($semi, $ganador);
        }

        $tercerLugar = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Tercer lugar')->first();
        $idsTercer = $tercerLugar->participantes()->pluck('id_inscripcion')->sort()->values()->all();
        sort($perdedores);
        $this->assertSame($perdedores, $idsTercer);
    }

    public function test_show_podio_null_si_final_sin_decidir(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->where('podio', null));
    }

    public function test_show_podio_con_final_decidida(): void
    {
        $categoria = $this->categoriaConAprobados(2); // solo final, sin tercer lugar
        $service = new BracketService;
        $service->generar($categoria);

        $final = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Final')->first();
        $ids = $final->participantes()->pluck('id_inscripcion')->all();
        $service->registrarGanador($final, $ids[0]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->has('podio')
                ->where('podio.tercero', null)
                ->whereNot('podio.campeon', null)
                ->whereNot('podio.subcampeon', null)
            );
    }

    public function test_show_podio_incluye_tercero(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        $service = new BracketService;
        $service->generar($categoria);

        // Decidir semifinales (enruta perdedores al tercer lugar).
        foreach (Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->get() as $semi) {
            $service->registrarGanador($semi, $semi->participantes()->pluck('id_inscripcion')->first());
        }
        // Decidir final y tercer lugar.
        $final = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Final')->first();
        $service->registrarGanador($final, $final->participantes()->pluck('id_inscripcion')->first());
        $tercer = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Tercer lugar')->first();
        $service->registrarGanador($tercer, $tercer->participantes()->pluck('id_inscripcion')->first());

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->whereNot('podio.tercero', null));
    }
}
