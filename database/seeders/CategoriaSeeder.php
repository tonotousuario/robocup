<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Mini Sumo Autónomo Amateur', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 350, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo Autónomo Profesional', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo RC Amateur', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 350, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo RC Profesional', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Micro Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 100, 'dimensiones_maximas' => '5x5 cm'],
            ['nombre' => 'Nano Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 25, 'dimensiones_maximas' => '2.5x2.5 cm'],
            ['nombre' => 'Seguidor de Línea Amateur', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '25x25 cm'],
            ['nombre' => 'Seguidor de Línea Profesional', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '25x25 cm'],
        ];

        foreach ($categorias as $categoria) {
            Categoria::firstOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }
    }
}
