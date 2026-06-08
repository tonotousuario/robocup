<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InspeccionTest extends TestCase
{
    use RefreshDatabase;

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_coach_recibe_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())->get('/inspecciones')->assertForbidden();
    }

    public function test_juez_ve_inscripciones_pagadas(): void
    {
        Inscripcion::factory()->pagada()->create();
        Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);

        $this->actingAs($this->juez())
            ->get('/inspecciones')
            ->assertInertia(fn (Assert $page) => $page->component('inspecciones/index')->has('inspecciones', 1));
    }

    public function test_piloto_solo_ve_sus_inscripciones(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        $miRobot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        Inscripcion::factory()->pagada()->create(['id_robot' => $miRobot->id_robot]);
        Inscripcion::factory()->pagada()->create();

        $this->actingAs($piloto)
            ->get('/inspecciones')
            ->assertInertia(fn (Assert $page) => $page->component('inspecciones/index')->has('inspecciones', 1));
    }

    public function test_juez_inspecciona_inscripcion_pagada(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
                'observaciones' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inspecciones_checklist', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'id_juez' => $juez->id,
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_re_inspeccionar_actualiza_la_misma_fila(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $payload = fn (string $estado) => [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'peso_medido_g' => 480,
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => $estado,
            'observaciones' => null,
        ];

        $this->actingAs($juez)->post('/inspecciones', $payload('Rechazado'))->assertRedirect();
        $this->actingAs($juez)->post('/inspecciones', $payload('Aprobado'))->assertRedirect();

        $this->assertSame(1, InspeccionChecklist::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
        $this->assertDatabaseHas('inspecciones_checklist', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_no_se_inspecciona_inscripcion_no_pagada(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
            ])
            ->assertSessionHasErrors('id_inscripcion');

        $this->assertDatabaseMissing('inspecciones_checklist', ['id_inscripcion' => $inscripcion->id_inscripcion]);
    }

    public function test_piloto_no_puede_guardar(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);

        $this->actingAs($piloto)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
            ])
            ->assertForbidden();
    }

    public function test_estado_invalido_es_rechazado(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Pendiente',
            ])
            ->assertSessionHasErrors('estado_aprobacion');
    }
}
