# RoboLeague — Cronómetro de tiempo de reparación · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cuenta regresiva de 5 min del tiempo de reparación, persistida (hora de inicio) y visible/sincronizada en el panel de Combate y en la Proyección.

**Architecture:** Nueva columna `inscripciones.reparacion_iniciada_en`; al iniciar reparación se fija la hora. La cuenta se computa en cliente desde ese timestamp (`inicio+300s − ahora`), igual en todas las pantallas. El combate `index` expone el inicio por participante; la proyección `show` expone las reparaciones activas de la categoría. Un hook `useCuentaRegresiva` formatea mm:ss con tick por segundo; reusa el polling de 5 s de la proyección.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v3, React 19, Tailwind v4, PHPUnit 12, Pint.

**Convenciones/contexto (verificado en main tras los merges):**
- Baseline: `main` tiene 157 pruebas (combate-rounds + proyección fusionados).
- `inscripciones.reparacion_usada` (bool) ya existe; `Inscripcion` `#[Fillable([... 'reparacion_usada'])]` + cast bool.
- `EncuentroController::marcarReparacion(Request,$inscripcion)` ya existe (guard `reparacion_usada` → error; set true). Ruta `inscripciones/{inscripcion}/reparacion` (PATCH). Policy `registrarGanador` (Juez+Admin).
- Combate `index` ya mapea `reparacion_usada` por participante (línea ~56).
- Panel `registrar-ganador-control.tsx` (`PanelEncuentro`) ya tiene un botón de reparación con `p.reparacion_usada`.
- `ProyeccionController::show(Categoria)` renderiza `proyeccion/combate` con categoria/encuentros/enVivo/posiciones. `proyeccion/combate.tsx` lee esas props + polling `POLL_MS=5000`.
- Constante 5 min = 300 s.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. RefreshDatabase; `Carbon::setTestNow` para el tiempo. Factories: `Categoria::factory()->combate()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `User::factory()->juez()/coach()`.

---

## File Structure

**Backend:**
- Create: `database/migrations/<ts>_add_reparacion_iniciada_en_to_inscripciones_table.php`
- Modify: `app/Models/Inscripcion.php` (fillable + cast)
- Modify: `app/Http/Controllers/EncuentroController.php` (`marcarReparacion` set hora; `index` expone inicio)
- Modify: `app/Http/Controllers/ProyeccionController.php` (`show` expone `reparacionesActivas`)
- Test: `tests/Feature/CronometroReparacionTest.php`

**Frontend:**
- Create: `resources/js/hooks/use-cuenta-regresiva.ts`
- Modify: `resources/js/types/models.ts` (`ParticipanteBracket.reparacion_iniciada_en`, `ReparacionActiva`)
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx` (cuenta en el botón de reparación)
- Modify: `resources/js/pages/proyeccion/combate.tsx` (franja de reparaciones activas)

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-cronometro-reparacion
```
Expected: `Switched to a new branch 'feature/roboleague-cronometro-reparacion'`.

---

## Task 1: Backend — columna, inicio en marcarReparacion, props (index + proyección)

**Files:**
- Create: `database/migrations/<ts>_add_reparacion_iniciada_en_to_inscripciones_table.php`
- Modify: `app/Models/Inscripcion.php`
- Modify: `app/Http/Controllers/EncuentroController.php`
- Modify: `app/Http/Controllers/ProyeccionController.php`
- Test: `tests/Feature/CronometroReparacionTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/CronometroReparacionTest.php`:
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

    public function test_iniciar_reparacion_fija_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $categoria = Categoria::factory()->combate()->create();
        $inscripcion = $this->inscripcionAprobada($categoria);

        $this->actingAs(User::factory()->juez()->create())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")
            ->assertRedirect();

        $fresh = $inscripcion->fresh();
        $this->assertTrue($fresh->reparacion_usada);
        $this->assertNotNull($fresh->reparacion_iniciada_en);
        $this->assertSame('2026-06-09 10:00:00', $fresh->reparacion_iniciada_en->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_reparacion_sigue_siendo_una_sola_vez(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $inscripcion = $this->inscripcionAprobada($categoria);
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")->assertRedirect();
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")->assertSessionHasErrors('reparacion');
    }

    public function test_index_de_combate_expone_reparacion_iniciada_en(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        $this->actingAs(User::factory()->juez()->create())
            ->get('/combate?categoria='.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('combate/index')
                ->has('encuentros.0.participantes.0.reparacion_iniciada_en')
            );
    }

    public function test_proyeccion_lista_reparaciones_activas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $categoria = Categoria::factory()->combate()->create();

        $activo = $this->inscripcionAprobada($categoria);
        $activo->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()]); // recién iniciada

        $vencido = $this->inscripcionAprobada($categoria);
        $vencido->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()->subMinutes(6)]); // hace 6 min

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('proyeccion/combate')
                ->has('reparacionesActivas', 1)
            );

        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=CronometroReparacionTest`
Expected: FAIL (columna/props inexistentes).

- [ ] **Step 3: Migración**

Run: `php artisan make:migration add_reparacion_iniciada_en_to_inscripciones_table --no-interaction`
```php
public function up(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->timestamp('reparacion_iniciada_en')->nullable();
    });
}

public function down(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->dropColumn('reparacion_iniciada_en');
    });
}
```

- [ ] **Step 4: Modelo `Inscripcion`**

En `app/Models/Inscripcion.php`:
- Añadir `'reparacion_iniciada_en'` al `#[Fillable([...])]` (queda `['id_robot','id_tarifa','monto_pagado','estado_pago','reparacion_usada','reparacion_iniciada_en']`).
- En `casts()`, añadir `'reparacion_iniciada_en' => 'datetime',`.

- [ ] **Step 5: `marcarReparacion` fija la hora**

En `app/Http/Controllers/EncuentroController.php`, en `marcarReparacion`, cambiar:
```php
        $inscripcion->update(['reparacion_usada' => true]);
```
por:
```php
        $inscripcion->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()]);
```

- [ ] **Step 6: `index` de combate expone el inicio**

En `app/Http/Controllers/EncuentroController.php`, en el `map` de `participantes` de `index`, añadir junto a `reparacion_usada`:
```php
                        'reparacion_iniciada_en' => $p->inscripcion?->reparacion_iniciada_en?->toIso8601String(),
```

- [ ] **Step 7: `ProyeccionController::show` expone `reparacionesActivas`**

En `app/Http/Controllers/ProyeccionController.php`:
- Añadir import `use App\Models\Inscripcion;` y una constante de clase `private const REPARACION_SEGUNDOS = 300;`.
- En `show`, añadir antes del `return` y pasarlo como prop:
```php
$reparacionesActivas = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoria->id_categoria))
    ->whereNotNull('reparacion_iniciada_en')
    ->where('reparacion_iniciada_en', '>=', now()->subSeconds(self::REPARACION_SEGUNDOS))
    ->with('robot')
    ->get()
    ->map(fn (Inscripcion $i) => [
        'robot' => $i->robot?->nombre,
        'reparacion_iniciada_en' => $i->reparacion_iniciada_en?->toIso8601String(),
    ])
    ->values();
```
y en el array de `Inertia::render('proyeccion/combate', [...])` añadir:
```php
            'reparacionesActivas' => $reparacionesActivas,
```

- [ ] **Step 8: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=CronometroReparacionTest`
Expected: PASS (4 tests). Si `assertInertia` se queja del manifiesto Vite, las páginas ya existen (no son placeholders), así que no debería; si acaso, `npm run build` una vez.

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Inscripcion.php app/Http/Controllers/EncuentroController.php app/Http/Controllers/ProyeccionController.php tests/Feature/CronometroReparacionTest.php
git commit -m "feat(reparacion): persistir hora de inicio y exponer reparaciones activas"
```

---

## Task 2: Frontend — hook de cuenta regresiva + tipos

**Files:**
- Create: `resources/js/hooks/use-cuenta-regresiva.ts`
- Modify: `resources/js/types/models.ts`

- [ ] **Step 1: Hook `useCuentaRegresiva`**

`resources/js/hooks/use-cuenta-regresiva.ts`:
```ts
import { useEffect, useState } from 'react';

function calcularRestante(finIso: string): number {
    const fin = Date.parse(finIso);
    if (Number.isNaN(fin)) {
        return 0;
    }
    return Math.max(0, Math.floor((fin - Date.now()) / 1000));
}

export function formatearMmss(segundos: number): string {
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

/** Cuenta regresiva (en segundos) hasta `finIso`; tick cada segundo. */
export function useCuentaRegresiva(finIso: string): { segundosRestantes: number; mmss: string } {
    const [segundosRestantes, setSegundosRestantes] = useState(() => calcularRestante(finIso));

    useEffect(() => {
        setSegundosRestantes(calcularRestante(finIso));
        const id = setInterval(() => setSegundosRestantes(calcularRestante(finIso)), 1000);
        return () => clearInterval(id);
    }, [finIso]);

    return { segundosRestantes, mmss: formatearMmss(segundosRestantes) };
}
```

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`:
- En `ParticipanteBracket`, añadir: `reparacion_iniciada_en: string | null;`.
- Añadir:
```ts
export type ReparacionActiva = {
    robot: string | null;
    reparacion_iniciada_en: string;
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS (el hook y tipos compilan; aún sin uso, pero TS no se queja de exports sin usar en estos archivos).

- [ ] **Step 4: Commit**

```bash
git add resources/js/hooks/use-cuenta-regresiva.ts resources/js/types/models.ts
git commit -m "feat(reparacion): hook useCuentaRegresiva y tipos"
```

---

## Task 3: UI — cuenta en el panel de combate y franja en la proyección

**Files:**
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx`
- Modify: `resources/js/pages/proyeccion/combate.tsx`

- [ ] **Step 1: Helper compartido para el "fin" (300 s)**

(Se calcula inline: `new Date(Date.parse(inicio) + 300_000).toISOString()`. Constante local `const REPARACION_MS = 300_000;` en cada archivo que lo use.)

- [ ] **Step 2: Cuenta en el panel de combate**

En `resources/js/components/combate/registrar-ganador-control.tsx`:
- Añadir import: `import { useCuentaRegresiva } from '@/hooks/use-cuenta-regresiva';`.
- Crear un subcomponente al final del archivo (mismo módulo) para el botón/contador de un participante (un componente para poder usar el hook por fila):
```tsx
const REPARACION_MS = 300_000;

function BotonReparacion({
    idInscripcion,
    robot,
    usada,
    iniciadaEn,
}: {
    idInscripcion: number;
    robot: string | null;
    usada: boolean;
    iniciadaEn: string | null;
}) {
    const finIso = iniciadaEn ? new Date(Date.parse(iniciadaEn) + REPARACION_MS).toISOString() : '';
    const { segundosRestantes, mmss } = useCuentaRegresiva(finIso);

    if (usada && iniciadaEn) {
        return (
            <span className="text-xs text-muted-foreground">
                {robot ?? '—'}: {segundosRestantes > 0 ? `reparación ${mmss}` : 'reparación terminada'}
            </span>
        );
    }

    return (
        <Button
            size="sm"
            variant="ghost"
            disabled={usada}
            onClick={() =>
                router.patch(
                    EncuentroController.marcarReparacion.url(idInscripcion),
                    {},
                    { preserveScroll: true, onError },
                )
            }
        >
            Iniciar reparación {robot ?? '—'} (5 min)
        </Button>
    );
}
```
- En el render del panel, reemplazar el bloque actual del botón de reparación (el `encuentro.participantes.map(...)` que hoy renderiza el `<Button ... disabled={p.reparacion_usada}>`) por:
```tsx
            <div className="flex flex-wrap gap-2">
                {encuentro.participantes.map((p) => (
                    <BotonReparacion
                        key={`rep-${p.id_inscripcion}`}
                        idInscripcion={p.id_inscripcion}
                        robot={p.robot}
                        usada={p.reparacion_usada}
                        iniciadaEn={p.reparacion_iniciada_en}
                    />
                ))}
            </div>
```
(`onError` y `EncuentroController`/`router` ya están importados en el archivo.)

- [ ] **Step 3: Franja de reparaciones activas en la proyección**

En `resources/js/pages/proyeccion/combate.tsx`:
- Añadir imports: `import { useCuentaRegresiva } from '@/hooks/use-cuenta-regresiva';` y el tipo `ReparacionActiva` desde `@/types`.
- Añadir `reparacionesActivas: ReparacionActiva[];` a `PageProps` y extraerlo de `usePage`.
- Añadir a `POLL_MS`/`ROTAR_MS` un `const REPARACION_MS = 300_000;`.
- Crear un subcomponente para una reparación (para usar el hook por fila):
```tsx
function FranjaReparacion({ robot, iniciadaEn }: { robot: string | null; iniciadaEn: string }) {
    const finIso = new Date(Date.parse(iniciadaEn) + REPARACION_MS).toISOString();
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
- En el JSX (antes del bloque de vistas, dentro del contenedor principal), añadir la franja:
```tsx
            {reparacionesActivas.length > 0 && (
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    {reparacionesActivas.map((r) => (
                        <FranjaReparacion key={r.reparacion_iniciada_en + (r.robot ?? '')} robot={r.robot} iniciadaEn={r.reparacion_iniciada_en} />
                    ))}
                </div>
            )}
```
(El polling de 5 s ya recarga `reparacionesActivas`; el tick por segundo lo da el hook.)

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/combate/registrar-ganador-control.tsx resources/js/pages/proyeccion/combate.tsx
git commit -m "feat(reparacion): cuenta regresiva en panel de combate y franja en proyeccion"
```

---

## Task 4: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (157 baseline + CronometroReparacionTest 4 = 161).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual manual**

Con `composer run dev`: como Juez en `/combate`, "Iniciar reparación {robot}" → ver la cuenta mm:ss decreciendo; en `/proyeccion/combate/{id}` (sin login) ver la franja con el mismo conteo (mismo robot, mismo mm:ss aprox.). Al pasar 5 min, el panel muestra "reparación terminada" y la franja desaparece.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(reparacion): verificacion integral cronometro" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Columna `reparacion_iniciada_en` (nullable) + cast → Task 1 ✓
- `marcarReparacion` fija la hora; sigue siendo una vez → Task 1 (test) ✓
- Combate `index` expone `reparacion_iniciada_en` → Task 1 ✓
- Proyección `show` expone `reparacionesActivas` (solo < 5 min) → Task 1 (test) ✓
- Hook `useCuentaRegresiva` + tipos (`reparacion_iniciada_en`, `ReparacionActiva`) → Task 2 ✓
- Panel de combate: iniciar + cuenta mm:ss / "terminada" → Task 3 ✓
- Proyección: franja con cuenta, refrescada por polling + tick → Task 3 ✓
- Misma cuenta en ambas (parten del timestamp persistido) → Tasks 2,3 ✓
- Autorización Juez+Admin intacta → Task 1 (cubierto por ruta existente; el test de combate-rounds ya verifica 403) ✓
- DoD: suite, build, pint, visual → Task 4 ✓

**Notas/riesgos:**
- (Reloj cliente vs servidor) la cuenta se computa con `Date.now()` del navegador contra el inicio del servidor; un desfase de reloj se reflejaría como pequeño descuadre — aceptable para uso operativo (documentado en la spec).
- (Wayfinder) `marcarReparacion.url(id)` ya se usaba con escalar en el panel; sin cambio.
- (Hook por fila) se usa un subcomponente por participante/reparación para poder llamar `useCuentaRegresiva` una vez por cada uno (no se pueden llamar hooks en un `.map` sin componente).
