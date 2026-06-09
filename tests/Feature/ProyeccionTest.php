<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProyeccionTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaCombateConBracket(int $n = 4): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }
        (new BracketService)->generar($categoria);

        return $categoria;
    }

    public function test_index_es_publico(): void
    {
        Categoria::factory()->combate()->create();

        // sin actingAs → invitado
        $this->get('/proyeccion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('proyeccion/index')->has('categoriasCombate'));
    }

    public function test_show_es_publico_y_trae_encuentros(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('proyeccion/combate')
                ->where('categoria.id_categoria', $categoria->id_categoria)
                ->has('encuentros', 3) // 2 semifinales + final
            );
    }

    public function test_show_identifica_el_encuentro_en_vivo(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);

        // con 4 aprobados hay 2 semifinales vigentes (2 participantes, sin ganador)
        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('enVivo'));

        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $this->assertNotNull($semi);
    }

    public function test_show_incluye_posiciones_de_tiempo(): void
    {
        $categoria = $this->categoriaCombateConBracket(2);

        // sembrar una categoría de tiempo con un intento
        $tiempo = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $tiempo->id_categoria]);
        $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        IntentoTiempo::create(['id_inscripcion' => $ins->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 9.500, 'penalizacion_segundos' => 0]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('posiciones'));
    }

    public function test_categoria_inexistente_da_404(): void
    {
        $this->get('/proyeccion/combate/999999')->assertNotFound();
    }
}
