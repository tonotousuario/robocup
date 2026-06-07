<?php

namespace Tests\Feature\Database;

use App\Models\Institucion;
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
}
