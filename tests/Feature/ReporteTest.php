<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function pagadaEnCategoria(Categoria $categoria, string $estado = 'Pagado', float $monto = 250): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);

        return Inscripcion::factory()->create([
            'id_robot' => $robot->id_robot,
            'estado_pago' => $estado,
            'monto_pagado' => $estado === 'Pagado' ? $monto : 0,
        ]);
    }

    public function test_coach_y_piloto_reciben_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())->get('/reportes')->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'Piloto']))->get('/reportes')->assertForbidden();
    }

    public function test_juez_ve_reportes_sin_caja(): void
    {
        $this->actingAs(User::factory()->juez()->create())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/index')
                ->where('puedeVerCaja', false)
                ->where('caja', null)
                ->has('posiciones')
                ->has('emparejamientos')
            );
    }

    public function test_admin_ve_caja_con_totales_y_desglose(): void
    {
        $categoria = Categoria::factory()->tiempo()->create(['nombre' => 'Seguidor']);
        $this->pagadaEnCategoria($categoria, 'Pagado', 250);
        $this->pagadaEnCategoria($categoria, 'Pagado', 150);
        $this->pagadaEnCategoria($categoria, 'Pendiente');
        $this->pagadaEnCategoria($categoria, 'Cancelado');

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/index')
                ->where('puedeVerCaja', true)
                ->where('caja.total_recaudado', '400.00')
                ->where('caja.pagadas', 2)
                ->where('caja.pendientes', 1)
                ->where('caja.canceladas', 1)
            );
    }

    public function test_posiciones_desde_la_vista(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        IntentoTiempo::create(['id_inscripcion' => $inscripcion->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 12.500, 'penalizacion_segundos' => 0]);

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posiciones.0.id_inscripcion', $inscripcion->id_inscripcion)
                ->where('posiciones.0.mejor_tiempo', '12.500')
            );
    }

    public function test_emparejamientos_solo_vigentes(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < 4; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }
        (new BracketService)->generar($categoria);

        // 4 aprobados → 2 semifinales vigentes (2 participantes, sin ganador)
        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page->has('emparejamientos', 2));

        // Marcar un ganador → ese encuentro deja de ser vigente
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        (new BracketService)->registrarGanador($semi, $semi->participantes()->first()->id_inscripcion);

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page->has('emparejamientos', 1));
    }
}
