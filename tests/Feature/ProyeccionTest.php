<?php

namespace Tests\Feature;

use App\Models\Amonestacion;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\RoundEncuentro;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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

    public function test_envivo_incluye_marcador_de_rounds(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        [$a, $b] = $semi->participantes()->pluck('id_inscripcion')->all();

        RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 1, 'id_inscripcion_ganador' => $a, 'repetido' => false]);
        RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 2, 'id_inscripcion_ganador' => null, 'repetido' => true]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('enVivo.marcador', 2)
                ->where('enVivo.marcador', function ($marcador) {
                    $col = collect($marcador);
                    // suma total de rounds = 1 (el repetido no cuenta)
                    if ($col->sum('rounds') !== 1) {
                        return false;
                    }

                    // el robot A tiene 1, el otro 0 — el marcador trae robot+rounds (no id);
                    // basta validar que exista exactamente una entrada con rounds=1 y una con rounds=0.
                    return $col->where('rounds', 1)->count() === 1 && $col->where('rounds', 0)->count() === 1;
                })
            );
    }

    public function test_envivo_incluye_amonestaciones(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $idB = $semi->participantes()->pluck('id_inscripcion')->last();
        $juez = User::factory()->juez()->create();

        Amonestacion::create([
            'id_encuentro' => $semi->id_encuentro,
            'id_inscripcion' => $idB,
            'id_juez' => $juez->id,
            'numero_round' => 1,
            'motivo' => 'Tocó el robot en el dohyo',
        ]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('enVivo.amonestaciones', 1)
                ->where('enVivo.amonestaciones.0.motivo', 'Tocó el robot en el dohyo')
            );
    }

    public function test_envivo_null_sin_encuentro_vigente(): void
    {
        // Categoría de combate sin bracket → no hay encuentros → enVivo null.
        $categoria = Categoria::factory()->combate()->create();

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enVivo', null));
    }
}
