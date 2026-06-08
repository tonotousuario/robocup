# RoboLeague Fase 2.4b — Brackets / Combate · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar brackets de eliminación simple para categorías de Combate desde los robots aprobados (siembra al azar + byes), con avance automático de ganadores.

**Architecture:** `BracketService` encapsula la generación del árbol (desde la Final hacia abajo, cableando `id_encuentro_siguiente`) y el avance de ganadores; `EncuentroPolicy` + `EncuentroController` exponen index/generar/registrarGanador. Frontend Inertia/React: bracket por columnas de ronda + control de ganador, vía Wayfinder.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Service para lógica de negocio (ver `TarifaService`). Controladores extienden `Controller` base + `use AuthorizesRequests;` + `$this->authorize()`. Policy auto-descubierta por nombre de modelo (`Encuentro`→`EncuentroPolicy`).
- `form.transform()` NO es encadenable en Inertia React (no se usa aquí). Wayfinder default imports; `resources/js/actions`/`resources/js/routes` gitignored (regenerar `php artisan wayfinder:generate`). Nav por rol. `ConfirmDeleteDialog` reutilizable. Errores `onError`→`toast`.
- Modelos: `Encuentro` `#[Fillable(['id_categoria','ronda','id_encuentro_siguiente'])]`, rel `participantes()`/`siguiente()`/`categoria()`; `ParticipanteEncuentro` PK compuesta `$incrementing=false`, `#[Fillable(['id_encuentro','id_inscripcion','puntos_obtenidos','es_ganador'])]`, casts `es_ganador`=bool, rel `inscripcion()`; `Inscripcion` rel `robot()`/`inspecciones()`; `Categoria` (`tipo_evaluacion`). Trigger T2: participante requiere inspección Aprobado.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase. Factories: `Categoria::factory()->combate()/tiempo()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `User::factory()->juez()/coach()`/`['rol'=>...]`.

---

## File Structure

**Backend:**
- Create: `app/Services/BracketService.php` — generación + avance.
- Create: `app/Policies/EncuentroPolicy.php`
- Create: `app/Http/Requests/GenerarBracketRequest.php`, `app/Http/Requests/RegistrarGanadorRequest.php`
- Create: `app/Http/Controllers/EncuentroController.php`
- Modify: `routes/web.php`

**Frontend:**
- Modify: `resources/js/types/models.ts` — `ParticipanteBracket`, `EncuentroBracket`, `CategoriaCombateOpcion`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Combate".
- Create: `resources/js/components/combate/registrar-ganador-control.tsx`
- Create: `resources/js/pages/combate/index.tsx`

**Tests:** `tests/Feature/BracketServiceTest.php`, `tests/Feature/EncuentroTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-4b
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-4b'`.

---

## Task 1: `BracketService` (generación del árbol + avance) y sus pruebas

**Files:**
- Create: `app/Services/BracketService.php`
- Test: `tests/Feature/BracketServiceTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/BracketServiceTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\ParticipanteEncuentro;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaCombate(): Categoria
    {
        return Categoria::factory()->combate()->create();
    }

    /** Crea $n inscripciones aprobadas en la categoría. */
    private function aprobados(Categoria $categoria, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        }
    }

    public function test_genera_bracket_de_cuatro(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);

        (new BracketService())->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $this->assertCount(3, $encuentros); // 2 semifinales + final

        $final = $encuentros->firstWhere('id_encuentro_siguiente', null);
        $this->assertSame('Final', $final->ronda);

        $semis = $encuentros->where('ronda', 'Semifinal');
        $this->assertCount(2, $semis);
        $semis->each(fn ($s) => $this->assertSame($final->id_encuentro, $s->id_encuentro_siguiente));

        // 4 participantes repartidos en las 2 semifinales
        $this->assertSame(4, ParticipanteEncuentro::whereIn('id_encuentro', $semis->pluck('id_encuentro'))->count());
    }

    public function test_genera_bracket_de_ocho(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 8);

        (new BracketService())->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $this->assertCount(7, $encuentros);
        $this->assertCount(4, $encuentros->where('ronda', 'Cuartos'));
        $this->assertCount(2, $encuentros->where('ronda', 'Semifinal'));
        $this->assertCount(1, $encuentros->where('ronda', 'Final'));
    }

    public function test_byes_se_autoavanzan_con_cinco(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 5); // size=8, rondas: Cuartos/Semifinal/Final, byes=3

        (new BracketService())->generar($categoria);

        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)->get();
        $cuartos = $encuentros->where('ronda', 'Cuartos');
        $semis = $encuentros->where('ronda', 'Semifinal');

        // 3 byes: 3 ganadores marcados en Cuartos y 3 participantes ya en Semifinal
        $this->assertSame(3, ParticipanteEncuentro::whereIn('id_encuentro', $cuartos->pluck('id_encuentro'))->where('es_ganador', true)->count());
        $this->assertSame(3, ParticipanteEncuentro::whereIn('id_encuentro', $semis->pluck('id_encuentro'))->count());
    }

    public function test_minimo_dos_aprobados(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 1);

        $this->expectException(\DomainException::class);

        (new BracketService())->generar($categoria);
    }

    public function test_categoria_no_combate_lanza_excepcion(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();

        $this->expectException(\InvalidArgumentException::class);

        (new BracketService())->generar($categoria);
    }

    public function test_regenerar_borra_el_anterior(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);

        $service = new BracketService();
        $service->generar($categoria);
        $service->generar($categoria);

        $this->assertSame(3, Encuentro::where('id_categoria', $categoria->id_categoria)->count());
    }

    public function test_registrar_ganador_avanza_al_siguiente(): void
    {
        $categoria = $this->categoriaCombate();
        $this->aprobados($categoria, 4);
        $service = new BracketService();
        $service->generar($categoria);

        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        $service->registrarGanador($semi, $ganador);

        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro,
            'id_inscripcion' => $ganador,
            'es_ganador' => true,
        ]);
        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro_siguiente,
            'id_inscripcion' => $ganador,
        ]);
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=BracketServiceTest`
Expected: FAIL (`Class "App\Services\BracketService" not found`).

- [ ] **Step 3: Implementar el servicio**

`app/Services/BracketService.php`:
```php
<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use Illuminate\Support\Facades\DB;

class BracketService
{
    public function generar(Categoria $categoria): void
    {
        if ($categoria->tipo_evaluacion !== 'Combate') {
            throw new \InvalidArgumentException('La categoría no es de combate.');
        }

        DB::transaction(function () use ($categoria) {
            Encuentro::where('id_categoria', $categoria->id_categoria)->delete();

            $inscripciones = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoria->id_categoria))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->pluck('id_inscripcion')
                ->shuffle()
                ->values();

            $n = $inscripciones->count();
            if ($n < 2) {
                throw new \DomainException('Se requieren al menos 2 robots aprobados.');
            }

            $size = 2 ** (int) ceil(log($n, 2));

            // Árbol desde la final hacia abajo.
            $final = Encuentro::create([
                'id_categoria' => $categoria->id_categoria,
                'ronda' => $this->nombreRonda(1),
                'id_encuentro_siguiente' => null,
            ]);

            $nivelActual = collect([$final]);
            $matchesNivel = 1;
            while ($matchesNivel < $size / 2) {
                $matchesNivel *= 2;
                $ronda = $this->nombreRonda($matchesNivel);
                $siguiente = collect();
                foreach ($nivelActual as $padre) {
                    for ($k = 0; $k < 2; $k++) {
                        $siguiente->push(Encuentro::create([
                            'id_categoria' => $categoria->id_categoria,
                            'ronda' => $ronda,
                            'id_encuentro_siguiente' => $padre->id_encuentro,
                        ]));
                    }
                }
                $nivelActual = $siguiente;
            }

            $ronda1 = $nivelActual->values(); // size/2 matches

            // Colocar competidores: primeros $byes matches reciben 1 (bye), el resto 2.
            $byes = $size - $n;
            $cursor = 0;
            foreach ($ronda1 as $index => $match) {
                $cuantos = $index < $byes ? 1 : 2;
                for ($s = 0; $s < $cuantos; $s++) {
                    ParticipanteEncuentro::create([
                        'id_encuentro' => $match->id_encuentro,
                        'id_inscripcion' => $inscripciones[$cursor],
                    ]);
                    $cursor++;
                }
            }

            // Auto-avance de byes.
            foreach ($ronda1 as $index => $match) {
                if ($index < $byes) {
                    $idInscripcion = $match->participantes()->first()->id_inscripcion;
                    $this->registrarGanador($match, $idInscripcion);
                }
            }
        });
    }

    public function registrarGanador(Encuentro $encuentro, int $idInscripcion): void
    {
        ParticipanteEncuentro::where('id_encuentro', $encuentro->id_encuentro)
            ->where('id_inscripcion', $idInscripcion)
            ->update(['es_ganador' => true]);

        if ($encuentro->id_encuentro_siguiente !== null) {
            ParticipanteEncuentro::firstOrCreate([
                'id_encuentro' => $encuentro->id_encuentro_siguiente,
                'id_inscripcion' => $idInscripcion,
            ]);
        }
    }

    private function nombreRonda(int $matches): string
    {
        return match ($matches) {
            1 => 'Final',
            2 => 'Semifinal',
            4 => 'Cuartos',
            8 => 'Octavos',
            16 => 'Dieciseisavos',
            default => 'Ronda de '.($matches * 2),
        };
    }
}
```

- [ ] **Step 4: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=BracketServiceTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/BracketService.php tests/Feature/BracketServiceTest.php
git commit -m "feat(combate): BracketService generacion de llaves y avance de ganadores"
```

---

## Task 2: Backend HTTP (policy, requests, controlador, rutas, tests)

**Files:**
- Create: `app/Policies/EncuentroPolicy.php`
- Create: `app/Http/Requests/GenerarBracketRequest.php`, `app/Http/Requests/RegistrarGanadorRequest.php`
- Create: `app/Http/Controllers/EncuentroController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/combate/index.tsx` (placeholder mínimo; se reemplaza en Task 4)
- Test: `tests/Feature/EncuentroTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/EncuentroTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EncuentroTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    private function categoriaConAprobados(int $n): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        }

        return $categoria;
    }

    public function test_todos_los_roles_ven_el_index(): void
    {
        $categoria = $this->categoriaConAprobados(2);

        foreach (['Administrador', 'Juez', 'Coach', 'Piloto'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get('/combate?categoria='.$categoria->id_categoria)
                ->assertOk();
        }
    }

    public function test_solo_admin_genera(): void
    {
        $categoria = $this->categoriaConAprobados(4);

        $this->actingAs($this->juez())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertForbidden();

        $this->assertSame(0, Encuentro::where('id_categoria', $categoria->id_categoria)->count());

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertRedirect();

        $this->assertSame(3, Encuentro::where('id_categoria', $categoria->id_categoria)->count());
    }

    public function test_generar_con_menos_de_dos_falla(): void
    {
        $categoria = $this->categoriaConAprobados(1);

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_generar_categoria_de_tiempo_falla(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();

        $this->actingAs($this->admin())
            ->post('/combate/generar', ['id_categoria' => $categoria->id_categoria])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_juez_y_admin_registran_ganador_y_avanza(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService())->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        $this->actingAs($this->juez())
            ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => $ganador])
            ->assertRedirect();

        $this->assertDatabaseHas('participantes_encuentro', [
            'id_encuentro' => $semi->id_encuentro_siguiente,
            'id_inscripcion' => $ganador,
        ]);
    }

    public function test_coach_y_piloto_no_registran_ganador(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService())->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $ganador = $semi->participantes()->first()->id_inscripcion;

        foreach (['Coach', 'Piloto'] as $rol) {
            $user = $rol === 'Coach' ? User::factory()->coach()->create() : User::factory()->create(['rol' => 'Piloto']);
            $this->actingAs($user)
                ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => $ganador])
                ->assertForbidden();
        }
    }

    public function test_no_se_marca_ganador_que_no_participa(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService())->generar($categoria);
        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();

        $this->actingAs($this->admin())
            ->patch('/encuentros/'.$semi->id_encuentro.'/ganador', ['id_inscripcion' => 999999])
            ->assertSessionHasErrors('id_inscripcion');
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=EncuentroTest`
Expected: FAIL (rutas/controlador/policy inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/combate/index.tsx`:
```tsx
export default function CombateIndex() {
    return <div>Combate</div>;
}
```

- [ ] **Step 4: Policy**

`app/Policies/EncuentroPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\User;

class EncuentroPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function generar(User $user): bool
    {
        return false;
    }

    public function registrarGanador(User $user): bool
    {
        return $user->isJuez();
    }
}
```

- [ ] **Step 5: Form Requests**

`app/Http/Requests/GenerarBracketRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarBracketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
        ];
    }
}
```

`app/Http/Requests/RegistrarGanadorRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarGanadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_inscripcion' => ['required', 'integer'],
        ];
    }
}
```

- [ ] **Step 6: Controlador**

`app/Http/Controllers/EncuentroController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarBracketRequest;
use App\Http\Requests\RegistrarGanadorRequest;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use App\Services\BracketService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EncuentroController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private BracketService $bracket) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Encuentro::class);

        $categorias = Categoria::where('tipo_evaluacion', 'Combate')->orderBy('nombre')->get(['id_categoria', 'nombre']);
        $categoriaSeleccionada = (int) $request->query('categoria', (string) ($categorias->first()->id_categoria ?? 0));

        $encuentros = collect();
        $aprobadosCount = 0;
        if ($categoriaSeleccionada > 0) {
            $encuentros = Encuentro::where('id_categoria', $categoriaSeleccionada)
                ->with(['participantes.inscripcion.robot'])
                ->get()
                ->map(fn (Encuentro $e) => [
                    'id_encuentro' => $e->id_encuentro,
                    'ronda' => $e->ronda,
                    'id_encuentro_siguiente' => $e->id_encuentro_siguiente,
                    'participantes' => $e->participantes->map(fn (ParticipanteEncuentro $p) => [
                        'id_inscripcion' => $p->id_inscripcion,
                        'robot' => $p->inscripcion?->robot?->nombre,
                        'es_ganador' => $p->es_ganador,
                    ])->values(),
                ])->values();

            $aprobadosCount = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoriaSeleccionada))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->count();
        }

        return Inertia::render('combate/index', [
            'categorias' => $categorias,
            'categoriaSeleccionada' => $categoriaSeleccionada > 0 ? $categoriaSeleccionada : null,
            'encuentros' => $encuentros,
            'puedeGenerar' => $request->user()->isAdministrador(),
            'puedeRegistrar' => $request->user()->isJuez() || $request->user()->isAdministrador(),
            'aprobadosCount' => $aprobadosCount,
        ]);
    }

    public function generar(GenerarBracketRequest $request): RedirectResponse
    {
        $this->authorize('generar', Encuentro::class);

        $categoria = Categoria::findOrFail($request->integer('id_categoria'));

        if ($categoria->tipo_evaluacion !== 'Combate') {
            return back()->withErrors(['id_categoria' => 'La categoría no es de combate.']);
        }

        try {
            $this->bracket->generar($categoria);
        } catch (\DomainException $e) {
            return back()->withErrors(['id_categoria' => $e->getMessage()]);
        }

        return back()->with('success', 'Bracket generado.');
    }

    public function registrarGanador(RegistrarGanadorRequest $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        $idInscripcion = $request->integer('id_inscripcion');
        $participantes = $encuentro->participantes;

        if ($participantes->count() < 2) {
            return back()->withErrors(['id_inscripcion' => 'El encuentro aún no tiene dos participantes.']);
        }

        if ($participantes->firstWhere('es_ganador', true) !== null) {
            return back()->withErrors(['id_inscripcion' => 'El encuentro ya tiene un ganador.']);
        }

        if (! $participantes->contains('id_inscripcion', $idInscripcion)) {
            return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
        }

        $this->bracket->registrarGanador($encuentro, $idInscripcion);

        return back()->with('success', 'Ganador registrado.');
    }
}
```

- [ ] **Step 7: Rutas**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\EncuentroController;`.
- Dentro de un grupo `['auth','verified']`:
```php
    Route::get('combate', [EncuentroController::class, 'index'])->name('combate.index');
    Route::post('combate/generar', [EncuentroController::class, 'generar'])->name('combate.generar');
    Route::patch('encuentros/{encuentro}/ganador', [EncuentroController::class, 'registrarGanador'])->name('encuentros.ganador');
```

- [ ] **Step 8: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=EncuentroTest`
Expected: PASS (7 tests). (Si `assertInertia`/`assertOk` se queja del manifiesto Vite, correr `npm run build` una vez por la página placeholder.)

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/EncuentroPolicy.php app/Http/Requests/GenerarBracketRequest.php app/Http/Requests/RegistrarGanadorRequest.php app/Http/Controllers/EncuentroController.php routes/web.php resources/js/pages/combate/index.tsx tests/Feature/EncuentroTest.php
git commit -m "feat(combate): controlador, policy y rutas de bracket"
```

---

## Task 3: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/EncuentroController.ts` (index/generar/registrarGanador) y `resources/js/routes/combate/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type ParticipanteBracket = {
    id_inscripcion: number;
    robot: string | null;
    es_ganador: boolean;
};

export type EncuentroBracket = {
    id_encuentro: number;
    ronda: string;
    id_encuentro_siguiente: number | null;
    participantes: ParticipanteBracket[];
};

export type CategoriaCombateOpcion = {
    id_categoria: number;
    nombre: string;
};
```

- [ ] **Step 3: Ítem de navegación "Combate"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `Swords` a los iconos importados de `lucide-react`.
- Añadir `import combate from '@/routes/combate';`.
- Añadir al array `mainNavItems` (después de "Tiempos"):
```tsx
    {
        title: 'Combate',
        href: combate.index(),
        icon: Swords,
        roles: ['Administrador', 'Juez', 'Coach', 'Piloto'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(combate): tipos frontend y navegacion"
```

---

## Task 4: UI del bracket (render por columnas + control de ganador + generar)

**Files:**
- Create: `resources/js/components/combate/registrar-ganador-control.tsx`
- Modify: `resources/js/pages/combate/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Control de ganador**

`resources/js/components/combate/registrar-ganador-control.tsx`:
```tsx
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import { Button } from '@/components/ui/button';
import type { EncuentroBracket } from '@/types';

type Props = {
    encuentro: EncuentroBracket;
};

export default function RegistrarGanadorControl({ encuentro }: Props) {
    const marcar = (idInscripcion: number) => {
        router.patch(
            EncuentroController.registrarGanador.url(encuentro.id_encuentro),
            { id_inscripcion: idInscripcion },
            {
                preserveScroll: true,
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    if (message) {
                        toast.error(message);
                    }
                },
            },
        );
    };

    return (
        <div className="mt-2 flex flex-col gap-1">
            {encuentro.participantes.map((p) => (
                <Button key={p.id_inscripcion} size="sm" variant="secondary" onClick={() => marcar(p.id_inscripcion)}>
                    Gana {p.robot ?? '—'}
                </Button>
            ))}
        </div>
    );
}
```

- [ ] **Step 2: Página índice**

Reemplazar `resources/js/pages/combate/index.tsx` por:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import RegistrarGanadorControl from '@/components/combate/registrar-ganador-control';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import combate from '@/routes/combate';
import type { CategoriaCombateOpcion, EncuentroBracket } from '@/types';

type PageProps = {
    categorias: CategoriaCombateOpcion[];
    categoriaSeleccionada: number | null;
    encuentros: EncuentroBracket[];
    puedeGenerar: boolean;
    puedeRegistrar: boolean;
    aprobadosCount: number;
};

const ORDEN_RONDAS = ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'];

export default function CombateIndex() {
    const { categorias, categoriaSeleccionada, encuentros, puedeGenerar, puedeRegistrar, aprobadosCount } =
        usePage<PageProps>().props;
    const [confirmOpen, setConfirmOpen] = useState(false);

    const cambiarCategoria = (id: string) => {
        router.get(combate.index().url, { categoria: id }, { preserveState: true, preserveScroll: true });
    };

    const generar = () => {
        if (!categoriaSeleccionada) {
            return;
        }
        router.post(
            EncuentroController.generar.url(),
            { id_categoria: categoriaSeleccionada },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmOpen(false),
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    if (message) {
                        toast.error(message);
                    }
                    setConfirmOpen(false);
                },
            },
        );
    };

    const rondas = ORDEN_RONDAS.filter((r) => encuentros.some((e) => e.ronda === r));

    return (
        <>
            <Head title="Combate" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Combate · Brackets</h1>
                    <div className="flex items-center gap-3">
                        {categorias.length > 0 && (
                            <Select
                                value={categoriaSeleccionada ? String(categoriaSeleccionada) : undefined}
                                onValueChange={cambiarCategoria}
                            >
                                <SelectTrigger className="w-56">
                                    <SelectValue placeholder="Categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categorias.map((c) => (
                                        <SelectItem key={c.id_categoria} value={String(c.id_categoria)}>
                                            {c.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        {puedeGenerar && categoriaSeleccionada && (
                            encuentros.length > 0 ? (
                                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                                    <DialogTrigger asChild>
                                        <Button disabled={aprobadosCount < 2}>Regenerar bracket</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Regenerar bracket</DialogTitle>
                                            <DialogDescription>
                                                Se borrará el bracket actual de esta categoría (incluidos los resultados) y se
                                                creará uno nuevo. ¿Continuar?
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">Cancelar</Button>
                                            </DialogClose>
                                            <Button variant="destructive" onClick={generar}>
                                                Regenerar
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            ) : (
                                <Button onClick={generar} disabled={aprobadosCount < 2}>
                                    Generar bracket
                                </Button>
                            )
                        )}
                    </div>
                </div>

                {puedeGenerar && categoriaSeleccionada && aprobadosCount < 2 && (
                    <p className="text-sm text-muted-foreground">
                        Se requieren al menos 2 robots aprobados para generar el bracket (hay {aprobadosCount}).
                    </p>
                )}

                {encuentros.length === 0 ? (
                    <p className="text-muted-foreground">No hay bracket generado para esta categoría.</p>
                ) : (
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {rondas.map((ronda) => (
                            <div key={ronda} className="flex min-w-56 flex-col gap-4">
                                <h2 className="text-sm font-semibold text-muted-foreground">{ronda}</h2>
                                {encuentros
                                    .filter((e) => e.ronda === ronda)
                                    .map((encuentro) => {
                                        const tieneGanador = encuentro.participantes.some((p) => p.es_ganador);
                                        return (
                                            <div
                                                key={encuentro.id_encuentro}
                                                className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                            >
                                                {encuentro.participantes.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">Por definir</p>
                                                ) : (
                                                    encuentro.participantes.map((p) => (
                                                        <p
                                                            key={p.id_inscripcion}
                                                            className={p.es_ganador ? 'font-semibold' : ''}
                                                        >
                                                            {p.robot ?? '—'} {p.es_ganador ? '✓' : ''}
                                                        </p>
                                                    ))
                                                )}
                                                {puedeRegistrar && encuentro.participantes.length === 2 && !tieneGanador && (
                                                    <RegistrarGanadorControl encuentro={encuentro} />
                                                )}
                                            </div>
                                        );
                                    })}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CombateIndex.layout = {
    breadcrumbs: [
        {
            title: 'Combate',
            href: combate.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/combate resources/js/pages/combate
git commit -m "feat(combate): UI de bracket por columnas y control de ganador"
```

---

## Task 5: Verificación integral de la Fase 2.4b

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (118 previos + BracketServiceTest 7 + EncuentroTest 7 = 132).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(combate): verificacion integral Fase 2.4b" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `EncuentroPolicy` (admin before; viewAny todos; generar false→admin; registrarGanador juez) → Task 2 ✓
- `BracketService::generar` (árbol desde la final, cableado siguiente, rondas por matches, byes al tope auto-avanzados, ≥2, Combate-only, regenerar borra) → Task 1 ✓
- `BracketService::registrarGanador` (es_ganador + firstOrCreate en siguiente) → Task 1 ✓
- `EncuentroController` index (selector categoría, encuentros mapeados, flags, aprobadosCount) + generar (guard Combate + DomainException) + registrarGanador (valida participante/2-participantes/sin-ganador) → Task 2 ✓
- Rutas index/generar/registrarGanador → Task 2 ✓
- Tipos + Wayfinder + nav "Combate" (4 roles) → Task 3 ✓
- UI: selector, generar/regenerar (confirm), bracket por columnas (orden de rondas), control de ganador (2 participantes + sin ganador + puedeRegistrar) → Task 4 ✓
- Pruebas: generación 4/8, byes 5, mínimo 2, no-combate, regenerar, registrar+avance, auth (admin genera, juez/admin registran, coach/piloto 403, todos ven), participante inválido → Tasks 1,2 ✓
- DoD: suite 100%, pint, build → Task 5 ✓

**Riesgos conocidos:**
- (Wayfinder) `@/routes/combate` y `@/actions/.../EncuentroController` existen tras `php artisan wayfinder:generate` (Task 3). `generar.url()` sin id; `registrarGanador.url(id_encuentro)` con escalar (como en fases previas); si no tipa, usar `{ encuentro: id }`.
- (`firstOrCreate` en PK compuesta) `ParticipanteEncuentro` no tiene PK incremental; `firstOrCreate(['id_encuentro'=>..,'id_inscripcion'=>..])` busca por ambas y crea si falta — comportamiento correcto para el avance idempotente. Si surge un problema con el modelo de PK compuesta, usar `ParticipanteEncuentro::where(...)->first() ?? ::create(...)`.
- (Manifiesto Vite) si `assertOk`/`assertInertia` fallan por la página placeholder, correr `npm run build` una vez (Task 2 Step 8).
