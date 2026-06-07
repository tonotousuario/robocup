<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use Illuminate\Database\Seeder;

class TarifaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tarifas = [
            ['descripcion' => 'Preventa', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31', 'monto' => 150.00],
            ['descripcion' => 'Fase Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250.00],
            ['descripcion' => 'Tardía', 'fecha_inicio_cobro' => '2026-06-01', 'fecha_fin_cobro' => '2026-06-30', 'monto' => 400.00],
            ['descripcion' => 'Demostración', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-12-31', 'monto' => 0.00],
        ];

        foreach ($tarifas as $tarifa) {
            Tarifa::create($tarifa);
        }
    }
}
