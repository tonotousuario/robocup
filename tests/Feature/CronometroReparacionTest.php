<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\ParticipanteEncuentro;
use App\Models\Robot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CronometroReparacionTest extends TestCase
{
    use RefreshDatabase;

    private function inscripcionAprobada(Categoria $categoria): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    public function test_iniciar_fija_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());

        $this->actingAs($this->juez())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")
            ->assertRedirect();

        $this->assertSame('2026-06-09 10:00:00', $inscripcion->fresh()->reparacion_iniciada_en->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_no_iniciar_si_ya_corre(): void
    {
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());
        $inscripcion->update(['reparacion_iniciada_en' => now()]);

        $this->actingAs($this->juez())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")
            ->assertSessionHasErrors('reparacion');
    }

    public function test_pausar_acumula_consumido(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());
        $juez = $this->juez();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-06-09 10:01:00')); // +60 s
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/pausar")->assertRedirect();

        $fresh = $inscripcion->fresh();
        $this->assertSame(60, $fresh->reparacion_segundos_consumidos);
        $this->assertNull($fresh->reparacion_iniciada_en);
        Carbon::setTestNow();
    }

    public function test_dos_tramos_se_suman(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());
        $juez = $this->juez();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:01:00')); // +60
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/pausar")->assertRedirect();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:01:30')); // +30
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/pausar")->assertRedirect();

        $this->assertSame(90, $inscripcion->fresh()->reparacion_segundos_consumidos);
        Carbon::setTestNow();
    }

    public function test_no_iniciar_sin_saldo(): void
    {
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());
        $inscripcion->update(['reparacion_segundos_consumidos' => 300]);

        $this->actingAs($this->juez())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")
            ->assertSessionHasErrors('reparacion');
    }

    public function test_pausar_sin_correr_falla(): void
    {
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());

        $this->actingAs($this->juez())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/pausar")
            ->assertSessionHasErrors('reparacion');
    }

    public function test_pausar_clampa_al_maximo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $inscripcion = $this->inscripcionAprobada(Categoria::factory()->combate()->create());
        $juez = $this->juez();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:06:40')); // +400 s
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/pausar")->assertRedirect();

        $this->assertSame(300, $inscripcion->fresh()->reparacion_segundos_consumidos);
        Carbon::setTestNow();
    }

    public function test_index_expone_restante(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        $this->actingAs($this->juez())
            ->get('/combate?categoria='.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('encuentros.0.participantes.0.reparacion_restante'));
    }

    public function test_proyeccion_lista_solo_las_corriendo(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $corriendo = $this->inscripcionAprobada($categoria);
        $corriendo->update(['reparacion_iniciada_en' => now()]);
        $pausada = $this->inscripcionAprobada($categoria);
        $pausada->update(['reparacion_segundos_consumidos' => 120, 'reparacion_iniciada_en' => null]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('reparacionesActivas', 1));
    }
}
