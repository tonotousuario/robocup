<?php

namespace Tests\Unit;

use App\Models\Tarifa;
use App\Services\TarifaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarifaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_la_tarifa_cuyo_rango_contiene_la_fecha(): void
    {
        Tarifa::factory()->create(['descripcion' => 'Preventa', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31', 'monto' => 150]);
        $regular = Tarifa::factory()->create(['descripcion' => 'Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250]);

        $tarifa = (new TarifaService)->vigentePara(Carbon::parse('2026-04-15'));

        $this->assertNotNull($tarifa);
        $this->assertSame($regular->id_tarifa, $tarifa->id_tarifa);
    }

    public function test_devuelve_null_si_ninguna_tarifa_cubre_la_fecha(): void
    {
        Tarifa::factory()->create(['fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31']);

        $tarifa = (new TarifaService)->vigentePara(Carbon::parse('2026-07-01'));

        $this->assertNull($tarifa);
    }

    public function test_vigente_para_hoy_usa_la_fecha_actual(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));
        $regular = Tarifa::factory()->create(['fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31']);

        $tarifa = (new TarifaService)->vigenteParaHoy();

        $this->assertNotNull($tarifa);
        $this->assertSame($regular->id_tarifa, $tarifa->id_tarifa);
        Carbon::setTestNow();
    }
}
