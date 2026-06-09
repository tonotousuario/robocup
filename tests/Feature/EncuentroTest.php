<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncuentroTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    private function categoriaConAprobados(int $n): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        }

        return $categoria;
    }

    public function test_todos_los_roles_ven_el_index(): void
    {
        $categoria = $this->categoriaConAprobados(2);

        foreach (['Administrador', 'Juez', 'Coach', 'Piloto'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get('/combate?categoria='.$categoria->id_categoria)
                ->assertOk();
        }
    }

    public function test_solo_admin_genera(): void
    {
        $categoria = $this->categoriaConAprobados(4);

        $this->actingAs($this->juez())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertForbidden();

        $this->assertSame(0, Encuentro::where('id_categoria', $categoria->id_categoria)->count());

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertRedirect();

        $this->assertSame(4, Encuentro::where('id_categoria', $categoria->id_categoria)->count()); // 2 semis + final + tercer lugar
    }

    public function test_generar_con_menos_de_dos_falla(): void
    {
        $categoria = $this->categoriaConAprobados(1);

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_generar_categoria_de_tiempo_falla(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_juez_y_admin_registran_ganador_y_avanza(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        $this->actingAs($this->juez())
            ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => $ganador])
            ->assertRedirect();

        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro_siguiente,
            'id_inscripcion' => $ganador,
        ]);
    }

    public function test_coach_y_piloto_no_registran_ganador(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        foreach (['Coach', 'Piloto'] as $rol) {
            $user = $rol === 'Coach' ? User::factory()->coach()->create() : User::factory()->create(['rol' => 'Piloto']);
            $this->actingAs($user)
                ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => $ganador])
                ->assertForbidden();
        }
    }

    public function test_no_se_marca_ganador_que_no_participa(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();

        $this->actingAs($this->admin())
            ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => 999999])
            ->assertSessionHasErrors('id_inscripcion');
    }
}
