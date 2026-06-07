<?php

namespace Tests\Feature\Database;

use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistasTest extends TestCase
{
    use RefreshDatabase;

    public function test_vista_posiciones_devuelve_el_mejor_tiempo_con_penalizacion(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inspeccion = InspeccionChecklist::factory()->aprobado()->create([
            'id_inscripcion' => Inscripcion::factory()->pagada()->create([
                'id_robot' => $robot->id_robot,
            ])->id_inscripcion,
        ]);
        $idInscripcion = $inspeccion->id_inscripcion;

        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 20.000]);
        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 2, 'tiempo_logrado' => 10.000, 'penalizacion_segundos' => 5.000]);
        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 3, 'tiempo_logrado' => 18.000]);

        $fila = DB::table('vista_posiciones')->where('id_inscripcion', $idInscripcion)->first();

        // mejor = min(20.000, 10+5=15.000, 18.000) = 15.000
        $this->assertEquals(15.000, (float) $fila->mejor_tiempo);
        $this->assertEquals(3, (int) $fila->intentos);
    }
}
