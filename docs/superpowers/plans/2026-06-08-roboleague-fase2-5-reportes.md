# RoboLeague Fase 2.5 — Reportes · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Una página de Reportes de solo lectura: caja financiera (solo Admin), posiciones y emparejamientos vigentes (Juez+Admin), usando las vistas de BD.

**Architecture:** Ruta `/reportes` protegida con `role:Administrador,Juez`; `ReporteController@index` arma caja (solo Admin) por Eloquent y posiciones/emparejamientos desde `vista_posiciones`/`vista_emparejamientos` (`DB::table`). Frontend Inertia/React: una página con secciones condicionales reusando `StatCard`, vía Wayfinder.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Controladores extienden `Controller` base. Aquí NO se usa Policy: la autorización es por middleware `role:Administrador,Juez` en la ruta; la sección Caja se gatea con un flag `puedeVerCaja` (= isAdministrador) calculado en el controlador.
- Wayfinder default imports; `resources/js/actions`/`resources/js/routes` gitignored (regenerar `php artisan wayfinder:generate`). Nav por rol (`NavItem.roles`). `StatCard` existe en `@/components/stat-card`.
- Vistas BD: `vista_posiciones` (`id_inscripcion,id_robot,robot,id_categoria,categoria,mejor_tiempo,intentos`), `vista_emparejamientos` (`id_encuentro,ronda,categoria,id_inscripcion,robot,puntos_obtenidos,es_ganador`).
- **Gotcha pgsql**: al leer `es_ganador` (boolean) con `DB::table`, castear en el SELECT `es_ganador::int` y comparar con entero, para no depender del parseo de booleanos.
- Modelos: `Inscripcion` (`estado_pago`,`monto_pagado`, rel `robot()`), `Robot` (rel `categoria()`), `Categoria`. Para tests: `BracketService`, factories `Categoria::factory()->tiempo()/combate()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `Robot::factory()`, `IntentoTiempo`, `User::factory()->juez()/coach()`.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase.

---

## File Structure

**Backend:**
- Create: `app/Http/Controllers/ReporteController.php`
- Modify: `routes/web.php`

**Frontend:**
- Modify: `resources/js/types/models.ts` — `CajaPorCategoria`, `ReporteCaja`, `PosicionReporte`, `EmparejamientoVigente`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Reportes".
- Create: `resources/js/pages/reportes/index.tsx`

**Tests:** `tests/Feature/ReporteTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-5
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-5'`.

---

## Task 1: Backend de Reportes (controlador, ruta, tests)

**Files:**
- Create: `app/Http/Controllers/ReporteController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/reportes/index.tsx` (placeholder mínimo; se reemplaza en Task 3)
- Test: `tests/Feature/ReporteTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/ReporteTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function pagadaEnCategoria(Categoria $categoria, string $estado = 'Pagado', float $monto = 250): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);

        return Inscripcion::factory()->create([
            'id_robot' => $robot->id_robot,
            'estado_pago' => $estado,
            'monto_pagado' => $estado === 'Pagado' ? $monto : 0,
        ]);
    }

    public function test_coach_y_piloto_reciben_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())->get('/reportes')->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'Piloto']))->get('/reportes')->assertForbidden();
    }

    public function test_juez_ve_reportes_sin_caja(): void
    {
        $this->actingAs(User::factory()->juez()->create())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/index')
                ->where('puedeVerCaja', false)
                ->where('caja', null)
                ->has('posiciones')
                ->has('emparejamientos')
            );
    }

    public function test_admin_ve_caja_con_totales_y_desglose(): void
    {
        $categoria = Categoria::factory()->tiempo()->create(['nombre' => 'Seguidor']);
        $this->pagadaEnCategoria($categoria, 'Pagado', 250);
        $this->pagadaEnCategoria($categoria, 'Pagado', 150);
        $this->pagadaEnCategoria($categoria, 'Pendiente');
        $this->pagadaEnCategoria($categoria, 'Cancelado');

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/index')
                ->where('puedeVerCaja', true)
                ->where('caja.total_recaudado', '400.00')
                ->where('caja.pagadas', 2)
                ->where('caja.pendientes', 1)
                ->where('caja.canceladas', 1)
            );
    }

    public function test_posiciones_desde_la_vista(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);
        IntentoTiempo::create(['id_inscripcion' => $inscripcion->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 12.500, 'penalizacion_segundos' => 0]);

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posiciones.0.id_inscripcion', $inscripcion->id_inscripcion)
                ->where('posiciones.0.mejor_tiempo', '12.500')
            );
    }

    public function test_emparejamientos_solo_vigentes(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < 4; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }
        (new BracketService)->generar($categoria);

        // 4 aprobados → 2 semifinales vigentes (2 participantes, sin ganador)
        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page->has('emparejamientos', 2));

        // Marcar un ganador → ese encuentro deja de ser vigente
        $semi = \App\Models\Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        (new BracketService)->registrarGanador($semi, $semi->participantes()->first()->id_inscripcion);

        $this->actingAs($this->admin())
            ->get('/reportes')
            ->assertInertia(fn (Assert $page) => $page->has('emparejamientos', 1));
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=ReporteTest`
Expected: FAIL (ruta/controlador inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/reportes/index.tsx`:
```tsx
export default function ReportesIndex() {
    return <div>Reportes</div>;
}
```

- [ ] **Step 4: Controlador**

`app/Http/Controllers/ReporteController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReporteController extends Controller
{
    public function index(Request $request): Response
    {
        $esAdmin = $request->user()->isAdministrador();

        return Inertia::render('reportes/index', [
            'puedeVerCaja' => $esAdmin,
            'caja' => $esAdmin ? $this->caja() : null,
            'posiciones' => $this->posiciones(),
            'emparejamientos' => $this->emparejamientosVigentes(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function caja(): array
    {
        return [
            'total_recaudado' => number_format((float) Inscripcion::where('estado_pago', 'Pagado')->sum('monto_pagado'), 2, '.', ''),
            'pagadas' => Inscripcion::where('estado_pago', 'Pagado')->count(),
            'pendientes' => Inscripcion::where('estado_pago', 'Pendiente')->count(),
            'canceladas' => Inscripcion::where('estado_pago', 'Cancelado')->count(),
            'por_categoria' => Categoria::orderBy('nombre')->get()->map(fn (Categoria $c) => [
                'categoria' => $c->nombre,
                'pagadas' => Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->count(),
                'recaudado' => number_format((float) Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->sum('monto_pagado'), 2, '.', ''),
            ])->values(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function posiciones(): \Illuminate\Support\Collection
    {
        return DB::table('vista_posiciones')
            ->orderBy('categoria')
            ->orderBy('mejor_tiempo')
            ->get()
            ->map(fn ($f) => [
                'id_inscripcion' => (int) $f->id_inscripcion,
                'robot' => $f->robot,
                'categoria' => $f->categoria,
                'mejor_tiempo' => $f->mejor_tiempo !== null ? (string) $f->mejor_tiempo : null,
                'intentos' => (int) $f->intentos,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function emparejamientosVigentes(): \Illuminate\Support\Collection
    {
        return DB::table('vista_emparejamientos')
            ->whereNotNull('id_inscripcion')
            ->select('id_encuentro', 'ronda', 'categoria', 'robot', DB::raw('es_ganador::int as ganador'))
            ->get()
            ->groupBy('id_encuentro')
            ->filter(fn ($filas) => $filas->count() === 2 && $filas->every(fn ($f) => (int) $f->ganador === 0))
            ->map(fn ($filas) => [
                'id_encuentro' => (int) $filas->first()->id_encuentro,
                'categoria' => $filas->first()->categoria,
                'ronda' => $filas->first()->ronda,
                'robots' => $filas->pluck('robot')->values(),
            ])
            ->values();
    }
}
```

- [ ] **Step 5: Ruta**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\ReporteController;`.
- Añadir:
```php
Route::middleware(['auth', 'verified', 'role:Administrador,Juez'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
});
```

- [ ] **Step 6: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=ReporteTest`
Expected: PASS (5 tests). (Si `assertInertia` se queja del manifiesto Vite, correr `npm run build` una vez por la página placeholder.)

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ReporteController.php routes/web.php resources/js/pages/reportes/index.tsx tests/Feature/ReporteTest.php
git commit -m "feat(reportes): backend de caja, posiciones y emparejamientos vigentes"
```

---

## Task 2: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/ReporteController.ts` y `resources/js/routes/reportes/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type CajaPorCategoria = {
    categoria: string;
    pagadas: number;
    recaudado: string;
};

export type ReporteCaja = {
    total_recaudado: string;
    pagadas: number;
    pendientes: number;
    canceladas: number;
    por_categoria: CajaPorCategoria[];
};

export type PosicionReporte = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    mejor_tiempo: string | null;
    intentos: number;
};

export type EmparejamientoVigente = {
    id_encuentro: number;
    categoria: string | null;
    ronda: string;
    robots: (string | null)[];
};
```

- [ ] **Step 3: Ítem de navegación "Reportes"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `BarChart3` a los iconos importados de `lucide-react`.
- Añadir `import reportes from '@/routes/reportes';`.
- Añadir al array `mainNavItems` (después de "Combate"):
```tsx
    {
        title: 'Reportes',
        href: reportes.index(),
        icon: BarChart3,
        roles: ['Administrador', 'Juez'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(reportes): tipos frontend y navegacion"
```

---

## Task 3: UI de Reportes (página con secciones)

**Files:**
- Modify: `resources/js/pages/reportes/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Página índice**

Reemplazar `resources/js/pages/reportes/index.tsx` por:
```tsx
import { Head, usePage } from '@inertiajs/react';
import StatCard from '@/components/stat-card';
import reportes from '@/routes/reportes';
import type { EmparejamientoVigente, PosicionReporte, ReporteCaja } from '@/types';

type PageProps = {
    puedeVerCaja: boolean;
    caja: ReporteCaja | null;
    posiciones: PosicionReporte[];
    emparejamientos: EmparejamientoVigente[];
};

function agrupar<T, K extends string | number>(items: T[], clave: (item: T) => K): Map<K, T[]> {
    const mapa = new Map<K, T[]>();
    for (const item of items) {
        const k = clave(item);
        const grupo = mapa.get(k) ?? [];
        grupo.push(item);
        mapa.set(k, grupo);
    }
    return mapa;
}

export default function ReportesIndex() {
    const { puedeVerCaja, caja, posiciones, emparejamientos } = usePage<PageProps>().props;

    const posicionesPorCategoria = agrupar(posiciones, (p) => p.categoria ?? '—');
    const emparejamientosPorCategoria = agrupar(emparejamientos, (e) => e.categoria ?? '—');

    return (
        <>
            <Head title="Reportes" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                {puedeVerCaja && caja && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold">Caja</h2>
                        <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                            <StatCard label="Total recaudado" value={`$${caja.total_recaudado}`} />
                            <StatCard label="Pagadas" value={caja.pagadas} />
                            <StatCard label="Pendientes" value={caja.pendientes} />
                            <StatCard label="Canceladas" value={caja.canceladas} />
                        </div>
                        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <tr>
                                        <th scope="col" className="p-3">Categoría</th>
                                        <th scope="col" className="p-3">Pagadas</th>
                                        <th scope="col" className="p-3">Recaudado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {caja.por_categoria.map((fila) => (
                                        <tr key={fila.categoria} className="border-b border-sidebar-border/40 last:border-0">
                                            <td className="p-3">{fila.categoria}</td>
                                            <td className="p-3">{fila.pagadas}</td>
                                            <td className="p-3">${fila.recaudado}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold">Posiciones</h2>
                    {posicionesPorCategoria.size === 0 ? (
                        <p className="text-muted-foreground">Aún no hay tiempos registrados.</p>
                    ) : (
                        [...posicionesPorCategoria.entries()].map(([categoria, filas]) => (
                            <div key={categoria}>
                                <h3 className="mb-2 text-sm font-medium text-muted-foreground">{categoria}</h3>
                                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                            <tr>
                                                <th scope="col" className="p-3">#</th>
                                                <th scope="col" className="p-3">Robot</th>
                                                <th scope="col" className="p-3">Mejor tiempo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filas.map((p, i) => (
                                                <tr key={p.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                                    <td className="p-3">{i + 1}</td>
                                                    <td className="p-3">{p.robot ?? '—'}</td>
                                                    <td className="p-3">{p.mejor_tiempo ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))
                    )}
                </section>

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold">Emparejamientos vigentes</h2>
                    {emparejamientosPorCategoria.size === 0 ? (
                        <p className="text-muted-foreground">No hay emparejamientos pendientes.</p>
                    ) : (
                        [...emparejamientosPorCategoria.entries()].map(([categoria, filas]) => (
                            <div key={categoria}>
                                <h3 className="mb-2 text-sm font-medium text-muted-foreground">{categoria}</h3>
                                <ul className="flex flex-col gap-2">
                                    {filas.map((e) => (
                                        <li
                                            key={e.id_encuentro}
                                            className="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                                        >
                                            <span className="text-muted-foreground">{e.ronda}: </span>
                                            {(e.robots[0] ?? '—')} vs {(e.robots[1] ?? '—')}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

ReportesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reportes',
            href: reportes.index(),
        },
    ],
};
```

- [ ] **Step 2: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/reportes
git commit -m "feat(reportes): UI de caja, posiciones y emparejamientos"
```

---

## Task 4: Verificación integral de la Fase 2.5

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (134 previos + ReporteTest 5 = 139).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(reportes): verificacion integral Fase 2.5" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Ruta `/reportes` con `role:Administrador,Juez`; Coach/Piloto 403 → Task 1 ✓
- Caja solo Admin (`puedeVerCaja`/`caja=null` para Juez): totales + desglose por categoría → Task 1 ✓
- Posiciones desde `vista_posiciones` (orden categoría/tiempo) → Task 1 ✓
- Emparejamientos vigentes desde `vista_emparejamientos` (2 participantes, sin ganador; cast `es_ganador::int`) → Task 1 ✓
- Tipos + Wayfinder + nav "Reportes" (Admin/Juez) → Task 2 ✓
- UI: secciones condicionales (Caja si admin), posiciones y emparejamientos agrupados, reuso de `StatCard`, solo lectura → Task 3 ✓
- Pruebas: auth (coach/piloto 403, juez sin caja, admin con caja), caja totales/desglose, posiciones vista, emparejamientos solo vigentes → Task 1 ✓
- DoD: suite 100%, pint, build → Task 4 ✓

**Riesgo conocido (Wayfinder):** `@/routes/reportes` y `@/actions/.../ReporteController` existen tras `php artisan wayfinder:generate` (Task 2 Step 1). `reportes.index()` no lleva parámetros. Si `assertInertia` falla en Task 1 por manifiesto Vite, correr `npm run build` una vez.
