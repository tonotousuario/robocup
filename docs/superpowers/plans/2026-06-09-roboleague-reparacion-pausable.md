# RoboLeague — Tiempo de reparación pausable (saldo de 5 min) · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) o superpowers:executing-plans para ejecutar tarea por tarea. Los pasos usan checkbox (`- [ ]`).

**Goal:** Cambiar la reparación de "una vez, 5 min de corrido" a un saldo de 300 s consumible en tramos con iniciar/pausar.

**Architecture:** Reemplazar `inscripciones.reparacion_usada` (bool) por `reparacion_segundos_consumidos` (int) + `reparacion_iniciada_en` (timestamp nullable = corriendo). Dos endpoints (`iniciar`/`pausar`); restante = `max(0, 300 − consumidos − transcurrido)` con clamp. El cambio de columna rompe esquema+controlador+tests a la vez, así que el backend cambia atómicamente en una sola tarea.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v3, React 19, Tailwind v4, PHPUnit 12, Pint.

**Convenciones/contexto (verificado en main):**
- Baseline: 164 pruebas. El cronómetro (`reparacion_usada` + `reparacion_iniciada_en`) está en `main`.
- `Inscripcion`: `#[Fillable([...,'reparacion_usada','reparacion_iniciada_en'])]`, casts `reparacion_usada=>boolean`, `reparacion_iniciada_en=>datetime`.
- `EncuentroController::marcarReparacion(Request,$inscripcion)` (PATCH `inscripciones/{inscripcion}/reparacion`): guard `reparacion_usada`→error; set usada+now. `index` map por participante expone `reparacion_usada` + `reparacion_iniciada_en` (líneas ~56-57).
- `ProyeccionController::show`: `reparacionesActivas` = inscripciones con `reparacion_iniciada_en` no null Y `>= now()->subSeconds(REPARACION_SEGUNDOS)` (const de clase 300), mapea `{robot, reparacion_iniciada_en}`. El polling de `proyeccion/combate.tsx` ya incluye `reparacionesActivas` en el `only` (fix reciente).
- Front: `registrar-ganador-control.tsx` tiene `BotonReparacion({idInscripcion,robot,usada,iniciadaEn})` + `const REPARACION_MS=300_000`; usa `useCuentaRegresiva` y `EncuentroController.marcarReparacion.url(id)`. `proyeccion/combate.tsx` tiene `FranjaReparacion({robot,iniciadaEn})`. Tipos `ParticipanteBracket.reparacion_usada/reparacion_iniciada_en`, `ReparacionActiva` en `@/types`.
- **Tests que tocan reparación (a migrar):** `CronometroReparacionTest` (entero), `CombateRoundsTest` (líneas 62-64 esquema, 180 endpoint en test 403, 229-240 `test_reparacion_una_sola_vez`).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. RefreshDatabase; `Carbon::setTestNow` para tiempo. Factories: `Categoria::factory()->combate()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `Encuentro::factory()`, `ParticipanteEncuentro`, `User::factory()->juez()/coach()`.

---

## File Structure

**Backend (Task 1, atómico):**
- Create: `database/migrations/<ts>_replace_reparacion_usada_with_consumidos_on_inscripciones.php`
- Modify: `app/Models/Inscripcion.php` (const + columnas + `reparacionRestante()`)
- Modify: `app/Http/Controllers/EncuentroController.php` (iniciar/pausar; index map)
- Modify: `app/Http/Controllers/ProyeccionController.php` (filtro corriendo + consumidos)
- Modify: `routes/web.php` (dos rutas)
- Modify: `tests/Feature/CronometroReparacionTest.php` (reescribir al nuevo modelo)
- Modify: `tests/Feature/CombateRoundsTest.php` (ajustar las 3 referencias)

**Frontend (Task 2):**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx`
- Modify: `resources/js/pages/proyeccion/combate.tsx`

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-reparacion-pausable
```
Expected: `Switched to a new branch 'feature/roboleague-reparacion-pausable'`. (Si ya existe la rama, continuar en ella.)

---

## Task 1: Backend del saldo pausable (atómico: esquema + modelo + controlador + rutas + tests)

**Files:** ver File Structure (backend).

- [ ] **Step 1: Migración (reemplazo de columna)**

Run: `php artisan make:migration replace_reparacion_usada_with_consumidos_on_inscripciones --no-interaction`
```php
public function up(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->integer('reparacion_segundos_consumidos')->default(0);
        $table->dropColumn('reparacion_usada');
    });
}

public function down(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->boolean('reparacion_usada')->default(false);
        $table->dropColumn('reparacion_segundos_consumidos');
    });
}
```
(`reparacion_iniciada_en` se mantiene como está.)

- [ ] **Step 2: Modelo `Inscripcion`**

En `app/Models/Inscripcion.php`:
- Añadir constante al inicio de la clase: `public const REPARACION_SEGUNDOS = 300;`
- En `#[Fillable([...])]`: quitar `'reparacion_usada'`, añadir `'reparacion_segundos_consumidos'`. Queda: `['id_robot','id_tarifa','monto_pagado','estado_pago','reparacion_segundos_consumidos','reparacion_iniciada_en']`.
- En `casts()`: quitar `'reparacion_usada' => 'boolean'`, añadir `'reparacion_segundos_consumidos' => 'integer'`. Mantener `'reparacion_iniciada_en' => 'datetime'`.
- Añadir método (con `use Illuminate\Support\Carbon;` si hace falta; `now()` ya disponible):
```php
public function reparacionRestante(): int
{
    $transcurrido = $this->reparacion_iniciada_en !== null
        ? (int) now()->diffInSeconds($this->reparacion_iniciada_en, true)
        : 0;

    return max(0, self::REPARACION_SEGUNDOS - $this->reparacion_segundos_consumidos - $transcurrido);
}
```

- [ ] **Step 3: Controlador — iniciar/pausar + index**

En `app/Http/Controllers/EncuentroController.php`:
- Reemplazar el método `marcarReparacion` por dos métodos:
```php
public function iniciarReparacion(Inscripcion $inscripcion): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($inscripcion->reparacion_iniciada_en !== null) {
        return back()->withErrors(['reparacion' => 'La reparación ya está corriendo.']);
    }

    if ($inscripcion->reparacionRestante() <= 0) {
        return back()->withErrors(['reparacion' => 'Sin tiempo de reparación disponible.']);
    }

    $inscripcion->update(['reparacion_iniciada_en' => now()]);

    return back()->with('success', 'Reparación iniciada.');
}

public function pausarReparacion(Inscripcion $inscripcion): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($inscripcion->reparacion_iniciada_en === null) {
        return back()->withErrors(['reparacion' => 'La reparación no está corriendo.']);
    }

    $transcurrido = (int) now()->diffInSeconds($inscripcion->reparacion_iniciada_en, true);
    $nuevoConsumido = min(Inscripcion::REPARACION_SEGUNDOS, $inscripcion->reparacion_segundos_consumidos + $transcurrido);

    $inscripcion->update([
        'reparacion_segundos_consumidos' => $nuevoConsumido,
        'reparacion_iniciada_en' => null,
    ]);

    return back()->with('success', 'Reparación pausada.');
}
```
- En el `index` map de participantes, reemplazar las dos líneas `'reparacion_usada' => ...` / `'reparacion_iniciada_en' => ...` por:
```php
                        'reparacion_segundos_consumidos' => $p->inscripcion?->reparacion_segundos_consumidos ?? 0,
                        'reparacion_iniciada_en' => $p->inscripcion?->reparacion_iniciada_en?->toIso8601String(),
                        'reparacion_restante' => $p->inscripcion?->reparacionRestante() ?? Inscripcion::REPARACION_SEGUNDOS,
```
(Añadir `use App\Models\Inscripcion;` al controlador si no está; ya se usa en marcarReparacion, así que está.)

- [ ] **Step 4: Rutas**

En `routes/web.php`, reemplazar la línea de `inscripciones/{inscripcion}/reparacion` por:
```php
    Route::patch('inscripciones/{inscripcion}/reparacion/iniciar', [EncuentroController::class, 'iniciarReparacion'])->name('inscripciones.reparacion.iniciar');
    Route::patch('inscripciones/{inscripcion}/reparacion/pausar', [EncuentroController::class, 'pausarReparacion'])->name('inscripciones.reparacion.pausar');
```

- [ ] **Step 5: Proyección — filtro "corriendo" + consumidos**

En `app/Http/Controllers/ProyeccionController.php`, reemplazar el bloque `$reparacionesActivas` por:
```php
$reparacionesActivas = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoria->id_categoria))
    ->whereNotNull('reparacion_iniciada_en')
    ->with('robot')
    ->get()
    ->map(fn (Inscripcion $i) => [
        'robot' => $i->robot?->nombre,
        'reparacion_iniciada_en' => $i->reparacion_iniciada_en?->toIso8601String(),
        'reparacion_segundos_consumidos' => $i->reparacion_segundos_consumidos,
    ])
    ->values();
```
(La constante `REPARACION_SEGUNDOS` de `ProyeccionController` queda sin uso tras quitar la ventana; eliminarla para mantener limpio, o dejarla si Pint no se queja — preferir eliminarla.)

- [ ] **Step 6: Reescribir `CronometroReparacionTest` al nuevo modelo**

Reemplazar el contenido de `tests/Feature/CronometroReparacionTest.php` por:
```php
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
```

- [ ] **Step 7: Ajustar `CombateRoundsTest` (3 referencias)**

En `tests/Feature/CombateRoundsTest.php`:
- Líneas ~62-64 (en `test_tablas_y_columnas_existen`): reemplazar el bloque que usa `reparacion_usada` por:
```php
        $this->assertSame(0, $a->fresh()->reparacion_segundos_consumidos);
        $a->update(['reparacion_segundos_consumidos' => 60]);
        $this->assertSame(60, $a->fresh()->reparacion_segundos_consumidos);
```
- Línea ~180 (dentro del test de 403 coach/piloto): cambiar el endpoint `"/inscripciones/{$a}/reparacion"` por `"/inscripciones/{$a}/reparacion/iniciar"`.
- `test_reparacion_una_sola_vez` (~229-240): este test ya no aplica (el saldo no es "una sola vez"). Reemplazar su cuerpo por una verificación del nuevo flujo (iniciar requiere saldo), renombrándolo:
```php
public function test_no_se_puede_iniciar_reparacion_sin_saldo(): void
{
    $categoria = Categoria::factory()->combate()->create();
    $inscripcion = $this->inscripcionAprobada($categoria);
    $inscripcion->update(['reparacion_segundos_consumidos' => 300]);

    $this->actingAs($this->juez())
        ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion/iniciar")
        ->assertSessionHasErrors('reparacion');
}
```
(Si `CombateRoundsTest` no tiene helper `inscripcionAprobada`/`juez`, usar los que ya existen en ese archivo; ver sus métodos privados. Si no existen, crear una inscripción aprobada inline como en otros tests del archivo.)

- [ ] **Step 8: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter='CronometroReparacionTest|CombateRoundsTest'`
Expected: PASS. Si `assertInertia` se queja del manifiesto Vite, correr `npm run build` una vez.

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Inscripcion.php app/Http/Controllers/EncuentroController.php app/Http/Controllers/ProyeccionController.php routes/web.php tests/Feature/CronometroReparacionTest.php tests/Feature/CombateRoundsTest.php
git commit -m "feat(reparacion): saldo de 5 min con iniciar/pausar (reemplaza reparacion_usada)"
```

---

## Task 2: Frontend — tipos + panel iniciar/pausar + franja con saldo

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx`
- Modify: `resources/js/pages/proyeccion/combate.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: `EncuentroController` generado incluye `iniciarReparacion` y `pausarReparacion` (ya no `marcarReparacion`). Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`:
- En `ParticipanteBracket`: quitar `reparacion_usada: boolean;`; añadir:
```ts
    reparacion_segundos_consumidos: number;
    reparacion_iniciada_en: string | null;
    reparacion_restante: number;
```
- En `ReparacionActiva`: añadir `reparacion_segundos_consumidos: number;` (mantener `robot` y `reparacion_iniciada_en`).

- [ ] **Step 3: Panel — `BotonReparacion` con iniciar/pausar/agotada**

En `resources/js/components/combate/registrar-ganador-control.tsx`:
- Cambiar el bloque que renderiza `<BotonReparacion>` para pasar los nuevos props:
```tsx
            <div className="flex flex-wrap gap-2">
                {encuentro.participantes.map((p) => (
                    <BotonReparacion
                        key={`rep-${p.id_inscripcion}`}
                        idInscripcion={p.id_inscripcion}
                        robot={p.robot}
                        consumidos={p.reparacion_segundos_consumidos}
                        iniciadaEn={p.reparacion_iniciada_en}
                        restante={p.reparacion_restante}
                    />
                ))}
            </div>
```
- Reemplazar el componente `BotonReparacion` por:
```tsx
const REPARACION_SEGUNDOS = 300;

function formatearMmss(segundos: number): string {
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function BotonReparacion({
    idInscripcion,
    robot,
    consumidos,
    iniciadaEn,
    restante,
}: {
    idInscripcion: number;
    robot: string | null;
    consumidos: number;
    iniciadaEn: string | null;
    restante: number;
}) {
    const finIso = iniciadaEn
        ? new Date(Date.parse(iniciadaEn) + (REPARACION_SEGUNDOS - consumidos) * 1000).toISOString()
        : '';
    const { mmss } = useCuentaRegresiva(finIso);

    if (iniciadaEn) {
        return (
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    router.patch(EncuentroController.pausarReparacion.url(idInscripcion), {}, { preserveScroll: true, onError })
                }
            >
                Pausar reparación {robot ?? '—'} ({mmss})
            </Button>
        );
    }

    if (restante <= 0) {
        return <span className="text-xs text-muted-foreground">{robot ?? '—'}: reparación agotada</span>;
    }

    return (
        <Button
            size="sm"
            variant="ghost"
            onClick={() =>
                router.patch(EncuentroController.iniciarReparacion.url(idInscripcion), {}, { preserveScroll: true, onError })
            }
        >
            Iniciar reparación {robot ?? '—'} ({formatearMmss(restante)} disp.)
        </Button>
    );
}
```
(Quitar el viejo `const REPARACION_MS = 300_000;` si queda sin uso. `useCuentaRegresiva` ya importado; `EncuentroController`/`router`/`Button`/`onError` ya en el archivo.)

- [ ] **Step 4: Franja de proyección con saldo**

En `resources/js/pages/proyeccion/combate.tsx`, el `FranjaReparacion` debe usar `consumidos` para el fin:
- En el `.map` de `reparacionesActivas`, pasar `consumidos`:
```tsx
                        <FranjaReparacion
                            key={r.reparacion_iniciada_en + (r.robot ?? '')}
                            robot={r.robot}
                            iniciadaEn={r.reparacion_iniciada_en}
                            consumidos={r.reparacion_segundos_consumidos}
                        />
```
- Reemplazar el componente `FranjaReparacion` por:
```tsx
const REPARACION_SEGUNDOS = 300;

function FranjaReparacion({ robot, iniciadaEn, consumidos }: { robot: string | null; iniciadaEn: string; consumidos: number }) {
    const finIso = new Date(Date.parse(iniciadaEn) + (REPARACION_SEGUNDOS - consumidos) * 1000).toISOString();
    const { segundosRestantes, mmss } = useCuentaRegresiva(finIso);

    if (segundosRestantes <= 0) {
        return null;
    }

    return (
        <span className="rounded-lg bg-amber-500/20 px-4 py-2 font-display text-2xl text-amber-300">
            🔧 {robot ?? '—'} · {mmss}
        </span>
    );
}
```
(Quitar el viejo `const REPARACION_MS = 300_000;` si queda sin uso en ese archivo.)

- [ ] **Step 5: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS (no quedan referencias a `marcarReparacion` ni a `reparacion_usada`).

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/combate/registrar-ganador-control.tsx resources/js/pages/proyeccion/combate.tsx
git commit -m "feat(reparacion): UI iniciar/pausar con saldo en panel y franja de proyeccion"
```

---

## Task 3: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (reportar total; el conteo cambia porque `CronometroReparacionTest` pasó de 4 a 9 tests y `CombateRoundsTest` ajustó 1).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: migrate (dev) y verificación visual**

Run: `php artisan migrate` (aplica el reemplazo de columna en la BD de dev).
Luego, con `composer run dev`: como Juez en `/combate`, "Iniciar reparación {robot}" → cuenta mm:ss + botón "Pausar"; pausar → el botón vuelve a "Iniciar ({restante} disp.)" con el saldo reducido; agotar el saldo → "reparación agotada". En `/proyeccion/combate/{id}` la franja aparece mientras corre y desaparece al pausar.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(reparacion): verificacion integral saldo pausable" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Modelo saldo (consumidos + iniciada_en) reemplaza el bool; `reparacionRestante()` con clamp [0,300] → Task 1 ✓
- iniciar/pausar (acumula, bloquea sin saldo / sin correr, clamp) → Task 1 (tests) ✓
- index expone `reparacion_restante`; proyección lista solo corriendo → Task 1 (tests) ✓
- Tipos + panel iniciar/pausar/agotada + franja con saldo → Task 2 ✓
- Autorización Juez+Admin; Coach/Piloto 403 (endpoint iniciar) → Task 1 (CombateRoundsTest ajustado) ✓
- Tests existentes migrados al nuevo modelo → Task 1 (Steps 6-7) ✓
- DoD: suite, build, pint, migrate → Task 3 ✓

**Notas/riesgos:**
- (Atómico) El reemplazo de columna obliga a cambiar esquema+controlador+tests juntos (Task 1 grande) para no dejar el suite roja entre tareas.
- (Migrar dev) Tras esto, correr `php artisan migrate` en la BD de dev antes del QA manual (la BD de testing se recrea sola). [recordatorio conocido del proyecto]
- (Carbon diffInSeconds) usar el 2º arg `true` (absoluto) para que el signo no afecte el cálculo del transcurrido.
- (Wayfinder) tras renombrar las acciones, `php artisan wayfinder:generate` regenera; el front usa `iniciarReparacion`/`pausarReparacion`. `marcarReparacion` desaparece — confirmar que ningún otro archivo lo referencia (`grep`).
- (`ProyeccionController::REPARACION_SEGUNDOS`) queda sin uso tras quitar la ventana; eliminar la constante.
