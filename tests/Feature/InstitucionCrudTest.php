<?php

namespace Tests\Feature;

use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitucionCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_no_admin_recibe_403(): void
    {
        $this->actingAs(User::factory()->juez()->create())
            ->get('/instituciones')
            ->assertForbidden();
    }

    public function test_admin_ve_el_indice(): void
    {
        Institucion::factory()->create(['nombre' => 'TESCHA']);

        $this->actingAs($this->admin())
            ->get('/instituciones')
            ->assertInertia(fn (Assert $page) => $page
                ->component('instituciones/index')
                ->has('instituciones', 1)
            );
    }

    public function test_admin_crea_institucion(): void
    {
        $this->actingAs($this->admin())
            ->post('/instituciones', ['nombre' => 'Tec', 'tipo' => 'Privada', 'estado' => 'Nuevo León'])
            ->assertRedirect();

        $this->assertDatabaseHas('instituciones', ['nombre' => 'Tec', 'tipo' => 'Privada']);
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post('/instituciones', ['nombre' => 'X', 'tipo' => 'Galactica', 'estado' => 'CDMX'])
            ->assertSessionHasErrors('tipo');
    }

    public function test_admin_actualiza_institucion(): void
    {
        $institucion = Institucion::factory()->create(['nombre' => 'Viejo']);

        $this->actingAs($this->admin())
            ->put("/instituciones/{$institucion->id_institucion}", ['nombre' => 'Nuevo', 'tipo' => 'Pública', 'estado' => 'México'])
            ->assertRedirect();

        $this->assertDatabaseHas('instituciones', ['id_institucion' => $institucion->id_institucion, 'nombre' => 'Nuevo']);
    }

    public function test_borrar_institucion_deja_null_en_robot(): void
    {
        $institucion = Institucion::factory()->create();
        $robot = Robot::factory()->create(['id_institucion' => $institucion->id_institucion]);

        $this->actingAs($this->admin())
            ->delete("/instituciones/{$institucion->id_institucion}")
            ->assertRedirect();

        $this->assertDatabaseMissing('instituciones', ['id_institucion' => $institucion->id_institucion]);
        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'id_institucion' => null]);
    }
}
