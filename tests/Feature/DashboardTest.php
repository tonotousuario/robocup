<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_ve_metricas_globales(): void
    {
        InspeccionChecklist::factory()->create(['estado_aprobacion' => 'Pendiente']);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('stats')
            );
    }

    public function test_coach_solo_ve_sus_robots(): void
    {
        $coach = User::factory()->coach()->create();
        $otro = User::factory()->coach()->create();
        $miRobot = Robot::factory()->create(['id_piloto' => $coach->id, 'nombre' => 'MiBot']);
        Robot::factory()->create(['id_piloto' => $otro->id, 'nombre' => 'AjenoBot']);

        $this->actingAs($coach)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('robots', 1)
                ->where('robots.0.nombre', 'MiBot')
            );
    }

    public function test_juez_ve_inspecciones_pendientes(): void
    {
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('stats')
            );
    }

    public function test_admin_recibe_acciones_rapidas_y_atencion(): void
    {
        Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('accionesRapidas')
                ->has('atencion')
                ->where('atencion', fn ($a) => collect($a)->contains(fn ($i) => $i['value'] >= 1))
            );
    }

    public function test_juez_recibe_encuentros_por_resolver_en_atencion(): void
    {
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('accionesRapidas')
                ->has('atencion')
            );
    }

    public function test_piloto_recibe_acciones_rapidas(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);

        $this->actingAs($piloto)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('accionesRapidas')
                ->has('robots')
            );
    }
}
