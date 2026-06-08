<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\Robot;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15'));
        Tarifa::factory()->create(['descripcion' => 'Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
        $this->actingAs(User::factory()->juez()->create())->get('/inscripciones')->assertForbidden();
        $this->actingAs(User::factory()->coach()->create())->get('/inscripciones')->assertForbidden();
    }

    public function test_piloto_solo_ve_sus_inscripciones(): void
    {
        $piloto = $this->piloto();
        $miRobot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        Inscripcion::factory()->create(['id_robot' => $miRobot->id_robot]);
        Inscripcion::factory()->create();

        $this->actingAs($piloto)
            ->get('/inscripciones')
            ->assertInertia(fn (Assert $page) => $page->component('inscripciones/index')->has('inscripciones', 1));
    }

    public function test_piloto_inscribe_su_robot_con_tarifa_vigente(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertRedirect();

        $this->assertDatabaseHas('inscripciones', [
            'id_robot' => $robot->id_robot,
            'estado_pago' => 'Pendiente',
            'monto_pagado' => 0,
        ]);
        $this->assertDatabaseHas('inscripciones', ['id_robot' => $robot->id_robot]);
        $inscripcion = Inscripcion::where('id_robot', $robot->id_robot)->first();
        $this->assertNotNull($inscripcion->id_tarifa);
    }

    public function test_piloto_no_puede_inscribir_robot_ajeno(): void
    {
        $robotAjeno = Robot::factory()->create();

        $this->actingAs($this->piloto())
            ->post('/inscripciones', ['id_robot' => $robotAjeno->id_robot])
            ->assertSessionHasErrors('id_robot');

        $this->assertDatabaseMissing('inscripciones', ['id_robot' => $robotAjeno->id_robot]);
    }

    public function test_sin_tarifa_vigente_se_bloquea(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01')); // fuera del rango de la tarifa
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertSessionHasErrors('id_robot');

        $this->assertDatabaseMissing('inscripciones', ['id_robot' => $robot->id_robot]);
    }

    public function test_duplicado_se_bloquea_y_re_inscripcion_tras_cancelar(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'estado_pago' => 'Pendiente']);

        // duplicado bloqueado
        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertSessionHasErrors('id_robot');
        $this->assertSame(1, Inscripcion::where('id_robot', $robot->id_robot)->count());

        // cancelar libera el robot
        $this->actingAs($this->admin())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/cancelar")
            ->assertRedirect();

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertRedirect();
        $this->assertSame(2, Inscripcion::where('id_robot', $robot->id_robot)->count());
    }

    public function test_admin_marca_pagado_con_monto_de_tarifa(): void
    {
        $robot = Robot::factory()->create();
        $tarifa = Tarifa::factory()->create(['monto' => 400, 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31']);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'id_tarifa' => $tarifa->id_tarifa, 'estado_pago' => 'Pendiente', 'monto_pagado' => 0]);

        $this->actingAs($this->admin())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/pagar")
            ->assertRedirect();

        $this->assertDatabaseHas('inscripciones', ['id_inscripcion' => $inscripcion->id_inscripcion, 'estado_pago' => 'Pagado', 'monto_pagado' => 400]);
    }

    public function test_piloto_no_puede_pagar_ni_cancelar(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'estado_pago' => 'Pendiente']);

        $this->actingAs($piloto)->patch("/inscripciones/{$inscripcion->id_inscripcion}/pagar")->assertForbidden();
        $this->actingAs($piloto)->patch("/inscripciones/{$inscripcion->id_inscripcion}/cancelar")->assertForbidden();
    }
}
