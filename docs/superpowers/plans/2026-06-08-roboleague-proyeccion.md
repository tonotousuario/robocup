# RoboLeague — Modo Proyección · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Una vista pública full-screen para proyectar el bracket de combate en vivo (auto-refresh), con 3 vistas seleccionables (bracket / marcador / rotar).

**Architecture:** `ProyeccionController` público (sin auth) sirve datos de bracket + encuentro en vivo + posiciones desde los modelos/vista existentes. Frontend Inertia/React: un layout `projection` full-screen (sin sidebar, navy, alto contraste, Chakra Petch XL) y una página que alterna vistas por query param y refresca con polling cada 5 s.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones/contexto:**
- **Depende de C0** (tema). Esta rama parte de `main` con C0 ya fusionado.
- Controladores extienden `Controller` base. **Sin policy** (rutas públicas, fuera de `auth`).
- `Categoria` PK = `id_categoria` → route-model binding por defecto usa esa columna (`{categoria}`).
- Layouts se asignan por nombre de página en `resources/js/app.tsx` (switch). Hoy: `welcome`→null, `auth/`→AuthLayout, `settings/`→[AppLayout,SettingsLayout], default→AppLayout. Añadiremos `proyeccion`→ProjectionLayout.
- Mapa de encuentros idéntico al de `EncuentroController@index`: `{id_encuentro, ronda, id_encuentro_siguiente, participantes:[{id_inscripcion, robot, es_ganador}]}`. Tipo TS `EncuentroBracket` ya existe en `@/types`.
- `vista_posiciones` (BD): `robot, categoria, mejor_tiempo` (string), etc.
- Wayfinder default imports; `resources/js/actions`/`resources/js/routes` gitignored (regenerar `php artisan wayfinder:generate`).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase. Factories: `Categoria::factory()->combate()/tiempo()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `Robot::factory()`, `IntentoTiempo`, `BracketService`.

---

## File Structure

**Backend:**
- Create: `app/Http/Controllers/ProyeccionController.php` (index + show, públicos).
- Modify: `routes/web.php` (2 rutas públicas, fuera de `auth`).

**Frontend:**
- Create: `resources/js/layouts/projection-layout.tsx` (full-screen, sin sidebar).
- Modify: `resources/js/app.tsx` (registrar layout `proyeccion`).
- Create: `resources/js/pages/proyeccion/index.tsx` (selector).
- Create: `resources/js/pages/proyeccion/combate.tsx` (pantalla de proyección con 3 vistas + polling).
- Create: `resources/js/components/proyeccion/projection-bracket.tsx` (árbol XL).
- Create: `resources/js/components/proyeccion/projection-standings.tsx` (posiciones XL).
- Modify: `resources/js/types/models.ts` (`ProyeccionEnVivo`, `ProyeccionPosicion`).

**Tests:** `tests/Feature/ProyeccionTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main (con C0 ya fusionado)**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-proyeccion
```
Expected: `Switched to a new branch 'feature/roboleague-proyeccion'`.
(Si `main` no tiene el tema C0 aún, detente y avisa: este trabajo depende de C0.)

---

## Task 1: Backend de proyección (controlador público, rutas, tests)

**Files:**
- Create: `app/Http/Controllers/ProyeccionController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/proyeccion/index.tsx` y `resources/js/pages/proyeccion/combate.tsx` (placeholders mínimos; se reemplazan en Tasks 3–4)
- Test: `tests/Feature/ProyeccionTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/ProyeccionTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProyeccionTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaCombateConBracket(int $n = 4): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }
        (new BracketService)->generar($categoria);

        return $categoria;
    }

    public function test_index_es_publico(): void
    {
        Categoria::factory()->combate()->create();

        // sin actingAs → invitado
        $this->get('/proyeccion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('proyeccion/index')->has('categoriasCombate'));
    }

    public function test_show_es_publico_y_trae_encuentros(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('proyeccion/combate')
                ->where('categoria.id_categoria', $categoria->id_categoria)
                ->has('encuentros', 3) // 2 semifinales + final
            );
    }

    public function test_show_identifica_el_encuentro_en_vivo(): void
    {
        $categoria = $this->categoriaCombateConBracket(4);

        // con 4 aprobados hay 2 semifinales vigentes (2 participantes, sin ganador)
        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('enVivo'));

        $semi = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
        $this->assertNotNull($semi);
    }

    public function test_show_incluye_posiciones_de_tiempo(): void
    {
        $categoria = $this->categoriaCombateConBracket(2);

        // sembrar una categoría de tiempo con un intento
        $tiempo = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $tiempo->id_categoria]);
        $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        IntentoTiempo::create(['id_inscripcion' => $ins->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 9.500, 'penalizacion_segundos' => 0]);

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page->has('posiciones'));
    }

    public function test_categoria_inexistente_da_404(): void
    {
        $this->get('/proyeccion/combate/999999')->assertNotFound();
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=ProyeccionTest`
Expected: FAIL (rutas/controlador inexistentes).

- [ ] **Step 3: Placeholders de páginas**

`resources/js/pages/proyeccion/index.tsx`:
```tsx
export default function ProyeccionIndex() {
    return <div>Proyección</div>;
}
```
`resources/js/pages/proyeccion/combate.tsx`:
```tsx
export default function ProyeccionCombate() {
    return <div>Proyección combate</div>;
}
```

- [ ] **Step 4: Controlador**

`app/Http/Controllers/ProyeccionController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\ParticipanteEncuentro;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProyeccionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('proyeccion/index', [
            'categoriasCombate' => Categoria::where('tipo_evaluacion', 'Combate')->orderBy('nombre')->get(['id_categoria', 'nombre']),
        ]);
    }

    public function show(Categoria $categoria): Response
    {
        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)
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

        return Inertia::render('proyeccion/combate', [
            'categoria' => ['id_categoria' => $categoria->id_categoria, 'nombre' => $categoria->nombre],
            'encuentros' => $encuentros,
            'enVivo' => $this->enVivo($encuentros),
            'posiciones' => $this->posiciones(),
        ]);
    }

    /**
     * El encuentro vigente (2 participantes, sin ganador) de la ronda más avanzada.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $encuentros
     * @return array<string, mixed>|null
     */
    private function enVivo(\Illuminate\Support\Collection $encuentros): ?array
    {
        $orden = ['Final' => 1, 'Semifinal' => 2, 'Cuartos' => 3, 'Octavos' => 4, 'Dieciseisavos' => 5];

        $vigentes = $encuentros
            ->filter(fn (array $e) => count($e['participantes']) === 2
                && collect($e['participantes'])->every(fn (array $p) => ! $p['es_ganador']))
            ->sortBy(fn (array $e) => $orden[$e['ronda']] ?? 99);

        $e = $vigentes->first();
        if ($e === null) {
            return null;
        }

        return [
            'id_encuentro' => $e['id_encuentro'],
            'ronda' => $e['ronda'],
            'robots' => collect($e['participantes'])->pluck('robot')->values(),
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
                'robot' => $f->robot,
                'categoria' => $f->categoria,
                'mejor_tiempo' => $f->mejor_tiempo !== null ? (string) $f->mejor_tiempo : null,
            ]);
    }
}
```

- [ ] **Step 5: Rutas (públicas, FUERA de `auth`)**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\ProyeccionController;`.
- Añadir, fuera de cualquier grupo `auth` (por ejemplo cerca de `Route::inertia('/', 'welcome')`):
```php
Route::get('proyeccion', [ProyeccionController::class, 'index'])->name('proyeccion.index');
Route::get('proyeccion/combate/{categoria}', [ProyeccionController::class, 'show'])->name('proyeccion.combate');
```

- [ ] **Step 6: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=ProyeccionTest`
Expected: PASS (5 tests). (Si `assertInertia`/`assertOk` se queja del manifiesto Vite por las páginas placeholder, correr `npm run build` una vez y reintentar.)

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProyeccionController.php routes/web.php resources/js/pages/proyeccion tests/Feature/ProyeccionTest.php
git commit -m "feat(proyeccion): backend publico de bracket en vivo y posiciones"
```

---

## Task 2: Layout de proyección + Wayfinder + tipos + registro

**Files:**
- Create: `resources/js/layouts/projection-layout.tsx`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/types/models.ts`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/routes/proyeccion/index.ts` y la acción de `ProyeccionController`. Sin errores.

- [ ] **Step 2: Layout de proyección**

`resources/js/layouts/projection-layout.tsx`:
```tsx
import AppLogoIcon from '@/components/app-logo-icon';

type ProjectionLayoutProps = {
    children: React.ReactNode;
};

export default function ProjectionLayout({ children }: ProjectionLayoutProps) {
    return (
        <div className="dark flex min-h-screen flex-col bg-background text-foreground">
            <header className="flex items-center gap-3 border-b border-sidebar-border/70 px-8 py-4">
                <div className="flex aspect-square size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-6" />
                </div>
                <span className="font-display text-2xl font-bold tracking-wide">RoboLeague</span>
            </header>
            <main className="flex-1 overflow-auto p-8">{children}</main>
        </div>
    );
}
```
(El `dark` forzado en el contenedor garantiza el tema navy aunque el visitante no tenga preferencia.)

- [ ] **Step 3: Registrar el layout `proyeccion` en `app.tsx`**

En `resources/js/app.tsx`:
- Añadir import: `import ProjectionLayout from '@/layouts/projection-layout';`.
- En el `switch` de `layout: (name) => {`, añadir un caso ANTES del `default`:
```tsx
            case name.startsWith('proyeccion/'):
                return ProjectionLayout;
```

- [ ] **Step 4: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type ProyeccionEnVivo = {
    id_encuentro: number;
    ronda: string;
    robots: (string | null)[];
};

export type ProyeccionPosicion = {
    robot: string | null;
    categoria: string | null;
    mejor_tiempo: string | null;
};
```

- [ ] **Step 5: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/layouts/projection-layout.tsx resources/js/app.tsx resources/js/types/models.ts
git commit -m "feat(proyeccion): layout full-screen, tipos y registro de layout"
```

---

## Task 3: Componentes de proyección (bracket XL + posiciones XL) y página selector

**Files:**
- Create: `resources/js/components/proyeccion/projection-bracket.tsx`
- Create: `resources/js/components/proyeccion/projection-standings.tsx`
- Modify: `resources/js/pages/proyeccion/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Bracket XL**

`resources/js/components/proyeccion/projection-bracket.tsx`:
```tsx
import type { EncuentroBracket } from '@/types';

const ORDEN_RONDAS = ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'];

type Props = {
    encuentros: EncuentroBracket[];
};

export default function ProjectionBracket({ encuentros }: Props) {
    const rondas = ORDEN_RONDAS.filter((r) => encuentros.some((e) => e.ronda === r));

    if (encuentros.length === 0) {
        return <p className="text-2xl text-muted-foreground">Bracket no generado.</p>;
    }

    return (
        <div className="flex gap-10 overflow-x-auto">
            {rondas.map((ronda) => (
                <div key={ronda} className="flex min-w-72 flex-col justify-center gap-6">
                    <h2 className="font-display text-xl font-bold uppercase tracking-widest text-muted-foreground">{ronda}</h2>
                    {encuentros
                        .filter((e) => e.ronda === ronda)
                        .map((encuentro) => (
                            <div
                                key={encuentro.id_encuentro}
                                className="rounded-xl border-2 border-sidebar-border/70 bg-card p-4"
                            >
                                {encuentro.participantes.length === 0 ? (
                                    <p className="text-2xl text-muted-foreground">Por definir</p>
                                ) : (
                                    encuentro.participantes.map((p) => (
                                        <p
                                            key={p.id_inscripcion}
                                            className={
                                                p.es_ganador
                                                    ? 'font-display text-3xl font-bold text-primary'
                                                    : 'font-display text-3xl text-foreground/80'
                                            }
                                        >
                                            {p.robot ?? '—'} {p.es_ganador ? '✓' : ''}
                                        </p>
                                    ))
                                )}
                            </div>
                        ))}
                </div>
            ))}
        </div>
    );
}
```

- [ ] **Step 2: Posiciones XL**

`resources/js/components/proyeccion/projection-standings.tsx`:
```tsx
import type { ProyeccionPosicion } from '@/types';

type Props = {
    posiciones: ProyeccionPosicion[];
};

export default function ProjectionStandings({ posiciones }: Props) {
    if (posiciones.length === 0) {
        return <p className="text-2xl text-muted-foreground">Sin tiempos registrados.</p>;
    }

    return (
        <table className="w-full text-left">
            <thead>
                <tr className="border-b-2 border-sidebar-border/70">
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">#</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Robot</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Categoría</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Mejor</th>
                </tr>
            </thead>
            <tbody>
                {posiciones.map((p, i) => (
                    <tr key={`${p.categoria}-${p.robot}-${i}`} className="border-b border-sidebar-border/40">
                        <td className="p-4 text-4xl font-bold text-primary">{i + 1}</td>
                        <td className="p-4 font-display text-3xl">{p.robot ?? '—'}</td>
                        <td className="p-4 text-2xl text-foreground/70">{p.categoria ?? '—'}</td>
                        <td className="p-4 text-3xl font-semibold">{p.mejor_tiempo ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
```

- [ ] **Step 3: Página selector**

Reemplazar `resources/js/pages/proyeccion/index.tsx` por:
```tsx
import { Head, Link } from '@inertiajs/react';
import proyeccion from '@/routes/proyeccion';
import type { CategoriaCombateOpcion } from '@/types';

type PageProps = {
    categoriasCombate: CategoriaCombateOpcion[];
};

export default function ProyeccionIndex({ categoriasCombate }: PageProps) {
    return (
        <>
            <Head title="Proyección" />
            <div className="mx-auto flex max-w-2xl flex-col gap-6">
                <h1 className="font-display text-4xl font-bold">Proyección de competición</h1>
                <p className="text-xl text-muted-foreground">Elige la categoría de combate a proyectar:</p>
                <div className="flex flex-col gap-3">
                    {categoriasCombate.length === 0 ? (
                        <p className="text-muted-foreground">No hay categorías de combate.</p>
                    ) : (
                        categoriasCombate.map((c) => (
                            <Link
                                key={c.id_categoria}
                                href={proyeccion.combate(c.id_categoria).url}
                                className="rounded-xl border-2 border-sidebar-border/70 bg-card p-5 font-display text-2xl transition-colors hover:border-primary"
                            >
                                {c.nombre}
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}
```
(`CategoriaCombateOpcion` ya existe en `@/types` desde la Fase 2.4b. `proyeccion.combate(id)` lo genera Wayfinder; si la firma escalar no tipa, usar `{ categoria: id }`.)

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/proyeccion resources/js/pages/proyeccion/index.tsx
git commit -m "feat(proyeccion): componentes XL de bracket/posiciones y selector"
```

---

## Task 4: Pantalla de proyección con 3 vistas + polling

**Files:**
- Modify: `resources/js/pages/proyeccion/combate.tsx` (reemplaza placeholder)

- [ ] **Step 1: Página de proyección**

Reemplazar `resources/js/pages/proyeccion/combate.tsx` por:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ProjectionBracket from '@/components/proyeccion/projection-bracket';
import ProjectionStandings from '@/components/proyeccion/projection-standings';
import { Button } from '@/components/ui/button';
import proyeccion from '@/routes/proyeccion';
import type { CategoriaCombateOpcion, EncuentroBracket, ProyeccionEnVivo, ProyeccionPosicion } from '@/types';

type Vista = 'bracket' | 'marcador' | 'rotar';

type PageProps = {
    categoria: CategoriaCombateOpcion;
    encuentros: EncuentroBracket[];
    enVivo: ProyeccionEnVivo | null;
    posiciones: ProyeccionPosicion[];
};

const POLL_MS = 5000;
const ROTAR_MS = 12000;

function vistaFromUrl(): Vista {
    if (typeof window === 'undefined') {
        return 'bracket';
    }
    const v = new URLSearchParams(window.location.search).get('vista');
    return v === 'marcador' || v === 'rotar' ? v : 'bracket';
}

export default function ProyeccionCombate() {
    const { categoria, encuentros, enVivo, posiciones } = usePage<PageProps>().props;
    const [vista, setVista] = useState<Vista>(vistaFromUrl);
    const [rotarMostrandoBracket, setRotarMostrandoBracket] = useState(true);

    // Auto-refresh de datos (polling).
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['encuentros', 'enVivo', 'posiciones'] });
        }, POLL_MS);
        return () => clearInterval(id);
    }, []);

    // Rotación de vista cuando vista === 'rotar'.
    useEffect(() => {
        if (vista !== 'rotar') {
            return;
        }
        const id = setInterval(() => setRotarMostrandoBracket((prev) => !prev), ROTAR_MS);
        return () => clearInterval(id);
    }, [vista]);

    const cambiarVista = (next: Vista) => {
        setVista(next);
        router.get(proyeccion.combate(categoria.id_categoria).url, { vista: next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const botones: { vista: Vista; label: string }[] = [
        { vista: 'bracket', label: 'Bracket' },
        { vista: 'marcador', label: 'Marcador' },
        { vista: 'rotar', label: 'Rotar' },
    ];

    return (
        <>
            <Head title={`Proyección · ${categoria.nombre}`} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="font-display text-4xl font-bold">{categoria.nombre}</h1>
                <div className="flex gap-2">
                    {botones.map((b) => (
                        <Button
                            key={b.vista}
                            variant={vista === b.vista ? 'default' : 'secondary'}
                            onClick={() => cambiarVista(b.vista)}
                        >
                            {b.label}
                        </Button>
                    ))}
                </div>
            </div>

            {vista === 'marcador' && enVivo && (
                <div className="mb-8 rounded-2xl border-2 border-primary bg-card p-8 text-center">
                    <p className="font-display text-xl uppercase tracking-widest text-muted-foreground">{enVivo.ronda} · En vivo</p>
                    <p className="mt-3 font-display text-5xl font-bold">
                        {(enVivo.robots[0] ?? '—')} <span className="text-primary">vs</span> {(enVivo.robots[1] ?? '—')}
                    </p>
                </div>
            )}

            {vista === 'rotar' && !rotarMostrandoBracket ? (
                <ProjectionStandings posiciones={posiciones} />
            ) : (
                <ProjectionBracket encuentros={encuentros} />
            )}
        </>
    );
}
```
(Notas: en `marcador` se muestra la franja en vivo + el bracket; en `rotar` alterna bracket/posiciones cada 12 s; el polling de datos (5 s) es independiente. `proyeccion.combate(id).url` — si la firma escalar de Wayfinder no tipa, usar `{ categoria: id }`.)

- [ ] **Step 2: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/proyeccion/combate.tsx
git commit -m "feat(proyeccion): pantalla con 3 vistas seleccionables y auto-refresh"
```

---

## Task 5: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (139 previos + ProyeccionTest 5 = 144).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual manual**

Con `composer run dev`, abrir `/proyeccion` (sin login, p. ej. en ventana privada): elegir una categoría de combate con bracket generado y confirmar:
- Layout full-screen sin sidebar, navy, marca RoboLeague arriba.
- Vista **Bracket** legible de lejos (texto XL, ganador en acento).
- Vista **Marcador**: franja "en vivo" + bracket.
- Vista **Rotar**: alterna bracket ↔ posiciones cada ~12 s.
- Al marcar un ganador en `/combate` (otra pestaña, como Juez), la proyección avanza en ~5 s sin recargar manualmente.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(proyeccion): verificacion integral" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Rutas públicas `/proyeccion` (index) y `/proyeccion/combate/{categoria}` (show) fuera de auth; 404 categoría inexistente → Task 1 ✓
- `show`: encuentros (igual forma que combate), `enVivo` (2 participantes sin ganador, ronda más avanzada), posiciones desde `vista_posiciones` → Task 1 ✓
- Layout `projection` full-screen sin sidebar, navy forzado, marca → Task 2 ✓
- 3 vistas seleccionables (bracket/marcador/rotar) por barra + query param `?vista=`; rotar 12 s; polling 5 s → Task 4 ✓
- Componentes XL (bracket + posiciones) alto contraste, Chakra Petch → Task 3 ✓
- Selector de categorías → Task 3 ✓
- Tipos + Wayfinder + registro de layout → Task 2 ✓
- Pruebas: público 200, show datos, enVivo, posiciones, 404 → Task 1 ✓
- DoD: suite 100%, build, pint, visual → Task 5 ✓

**Riesgos conocidos:**
- (Dependencia C0) Si `main` no tiene el tema C0, `font-display`/colores no se verán; Task 0 avisa de detenerse.
- (Wayfinder) `proyeccion.combate(id)` con escalar; si no tipa, usar `{ categoria: id }`.
- (Manifiesto Vite) si `assertOk`/`assertInertia` fallan por las páginas placeholder, correr `npm run build` una vez (Task 1 Step 6).
- (Polling + reload) `router.reload({ only: [...] })` repuebla esas props sin recargar toda la página; el estado local `vista` se preserva porque no se remonta el componente.
