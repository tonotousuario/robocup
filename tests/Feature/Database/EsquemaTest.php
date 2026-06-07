<?php

namespace Tests\Feature\Database;

use App\Models\Categoria;
use App\Models\Institucion;
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
}
