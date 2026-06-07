<?php

namespace Tests\Feature\Database;

use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Institucion;
use App\Models\Robot;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_crear_una_institucion(): void
    {
        $institucion = Institucion::factory()->create(['nombre' => 'TESCHA']);

        $this->assertDatabaseHas('instituciones', ['nombre' => 'TESCHA']);
        $this->assertNotNull($institucion->id_institucion);
    }

    public function test_se_puede_crear_una_categoria(): void
    {
        Categoria::factory()->create(['nombre' => 'Mini Sumo']);

        $this->assertDatabaseHas('categorias', ['nombre' => 'Mini Sumo']);
    }

    public function test_tipo_evaluacion_invalido_es_rechazado_por_check(): void
    {
        $this->expectException(QueryException::class);

        Categoria::factory()->create(['tipo_evaluacion' => 'Invalido']);
    }

    public function test_usuario_tiene_columnas_de_roboleague(): void
    {
        $juez = User::factory()->juez()->create(['apellidos' => 'Pérez']);

        $this->assertDatabaseHas('users', ['apellidos' => 'Pérez', 'rol' => 'Juez']);
    }

    public function test_rol_invalido_es_rechazado_por_check(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['rol' => 'Hacker']);
    }

    public function test_se_puede_crear_un_robot_con_relaciones(): void
    {
        $robot = Robot::factory()->create(['nombre' => 'Trueno']);

        $this->assertDatabaseHas('robots', ['nombre' => 'Trueno']);
        $this->assertInstanceOf(User::class, $robot->piloto);
        $this->assertInstanceOf(Categoria::class, $robot->categoria);
    }

    public function test_se_puede_crear_una_tarifa(): void
    {
        Tarifa::factory()->create(['descripcion' => 'Preventa', 'monto' => 150.00]);

        $this->assertDatabaseHas('tarifas', ['descripcion' => 'Preventa']);
    }

    public function test_se_puede_crear_una_inscripcion(): void
    {
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->assertDatabaseHas('inscripciones', ['estado_pago' => 'Pagado']);
        $this->assertInstanceOf(Robot::class, $inscripcion->robot);
    }

    public function test_se_puede_crear_una_inspeccion_sobre_inscripcion_pagada(): void
    {
        InspeccionChecklist::factory()->aprobado()->create();

        $this->assertDatabaseHas('inspecciones_checklist', ['estado_aprobacion' => 'Aprobado']);
    }
}
