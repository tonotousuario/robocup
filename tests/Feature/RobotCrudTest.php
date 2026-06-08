<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RobotCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function piloto(): User
    {
        return User::factory()->create(['rol' => 'Piloto']);
    }

    public function test_juez_y_coach_reciben_403(): void
    {
        $this->actingAs(User::factory()->juez()->create())->get('/robots')->assertForbidden();
        $this->actingAs(User::factory()->coach()->create())->get('/robots')->assertForbidden();
    }

    public function test_admin_ve_todos_los_robots(): void
    {
        Robot::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->get('/robots')
            ->assertInertia(fn (Assert $page) => $page->component('robots/index')->has('robots', 2));
    }

    public function test_piloto_solo_ve_sus_robots(): void
    {
        $piloto = $this->piloto();
        Robot::factory()->create(['id_piloto' => $piloto->id, 'nombre' => 'MiBot']);
        Robot::factory()->create(['nombre' => 'AjenoBot']);

        $this->actingAs($piloto)
            ->get('/robots')
            ->assertInertia(fn (Assert $page) => $page
                ->component('robots/index')
                ->has('robots', 1)
                ->where('robots.0.nombre', 'MiBot')
            );
    }

    public function test_admin_crea_robot_para_un_piloto(): void
    {
        $piloto = $this->piloto();
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin())
            ->post('/robots', [
                'nombre' => 'Rayo', 'id_categoria' => $categoria->id_categoria,
                'id_institucion' => null, 'id_piloto' => $piloto->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['nombre' => 'Rayo', 'id_piloto' => $piloto->id]);
    }

    public function test_piloto_se_auto_asigna_aunque_envie_otro_id(): void
    {
        $piloto = $this->piloto();
        $otro = $this->piloto();
        $categoria = Categoria::factory()->create();

        $this->actingAs($piloto)
            ->post('/robots', [
                'nombre' => 'Propio', 'id_categoria' => $categoria->id_categoria,
                'id_institucion' => null, 'id_piloto' => $otro->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['nombre' => 'Propio', 'id_piloto' => $piloto->id]);
        $this->assertDatabaseMissing('robots', ['nombre' => 'Propio', 'id_piloto' => $otro->id]);
    }

    public function test_piloto_no_puede_editar_robot_ajeno(): void
    {
        $ajeno = Robot::factory()->create();
        $categoria = $ajeno->id_categoria;

        $this->actingAs($this->piloto())
            ->put("/robots/{$ajeno->id_robot}", [
                'nombre' => 'Hackeado', 'id_categoria' => $categoria, 'id_institucion' => null,
            ])
            ->assertForbidden();
    }

    public function test_piloto_edita_su_robot(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->put("/robots/{$robot->id_robot}", [
                'nombre' => 'Mejorado', 'id_categoria' => $robot->id_categoria, 'id_institucion' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'nombre' => 'Mejorado']);
    }

    public function test_admin_borra_cualquier_robot(): void
    {
        $robot = Robot::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/robots/{$robot->id_robot}")
            ->assertRedirect();

        $this->assertDatabaseMissing('robots', ['id_robot' => $robot->id_robot]);
    }

    public function test_id_categoria_es_requerido(): void
    {
        $this->actingAs($this->admin())
            ->post('/robots', ['nombre' => 'SinCat', 'id_piloto' => $this->piloto()->id])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_id_piloto_debe_ser_rol_piloto(): void
    {
        $juez = User::factory()->juez()->create();
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin())
            ->post('/robots', [
                'nombre' => 'X', 'id_categoria' => $categoria->id_categoria, 'id_piloto' => $juez->id,
            ])
            ->assertSessionHasErrors('id_piloto');
    }
}
