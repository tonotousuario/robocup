<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
