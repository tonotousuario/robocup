<?php

namespace Tests\Feature\Database;

use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\ParticipanteEncuentro;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_t1_inspeccion_sobre_inscripcion_no_pagada_es_bloqueada(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);
        $juez = User::factory()->juez()->create();

        $this->expectException(QueryException::class);

        InspeccionChecklist::create([
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'id_juez' => $juez->id,
            'peso_medido_g' => 500,
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_t2_participante_sin_inspeccion_aprobada_es_bloqueado(): void
    {
        $inscripcion = Inscripcion::factory()->pagada()->create();
        $encuentro = Encuentro::factory()->create();

        $this->expectException(QueryException::class);

        ParticipanteEncuentro::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'id_inscripcion' => $inscripcion->id_inscripcion,
        ]);
    }

    public function test_t3_tiempo_sin_inspeccion_aprobada_es_bloqueado(): void
    {
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->expectException(QueryException::class);

        IntentoTiempo::create([
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'numero_vuelta' => 1,
            'tiempo_logrado' => 12.500,
        ]);
    }

    public function test_t3_tiempo_con_inspeccion_aprobada_es_permitido(): void
    {
        $inspeccion = InspeccionChecklist::factory()->aprobado()->create();

        IntentoTiempo::create([
            'id_inscripcion' => $inspeccion->id_inscripcion,
            'numero_vuelta' => 1,
            'tiempo_logrado' => 12.500,
        ]);

        $this->assertDatabaseHas('intentos_tiempos', ['numero_vuelta' => 1]);
    }
}
