<?php

namespace Database\Seeders;

use App\Models\Institucion;
use Illuminate\Database\Seeder;

class InstitucionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $instituciones = [
            ['nombre' => 'TESCHA', 'tipo' => 'Pública', 'estado' => 'México'],
            ['nombre' => 'Tec de Monterrey', 'tipo' => 'Privada', 'estado' => 'Nuevo León'],
            ['nombre' => 'Club Robótica Independiente', 'tipo' => 'Independiente', 'estado' => 'CDMX'],
        ];

        foreach ($instituciones as $institucion) {
            Institucion::create($institucion);
        }
    }
}
