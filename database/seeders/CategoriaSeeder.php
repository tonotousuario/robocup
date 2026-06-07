<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Mini Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Guerra', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 30000, 'dimensiones_maximas' => '60x60 cm'],
            ['nombre' => 'Seguidor de Línea', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '20x20 cm'],
            ['nombre' => 'Laberinto', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '20x20 cm'],
        ];

        foreach ($categorias as $categoria) {
            Categoria::create($categoria);
        }
    }
}
