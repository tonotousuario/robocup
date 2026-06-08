# RoboLeague Fase 2.4a — Captura de Tiempos y Posiciones · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capturar hasta 3 intentos cronometrados por inscripción aprobada de categorías de Tiempo y mostrar la tabla de posiciones por mejor tiempo.

**Architecture:** `IntentoTiempoPolicy` (auto-descubierta por nombre) + `TiempoController` (trait `AuthorizesRequests`) con index (selector de categoría por `?categoria=`, ranking calculado en PHP) y `guardar` (updateOrCreate por vuelta, guardas de categoría-Tiempo y Aprobado; trigger T3/CHECK como candado final). Frontend Inertia/React: una tabla captura+ranking + modal de 3 vueltas, vía Wayfinder.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Controladores extienden `App\Http\Controllers\Controller` + `use AuthorizesRequests;` + `$this->authorize(...)` (base Controller plano; ver `InspeccionController`).
- Policy auto-descubierta: nombrar `IntentoTiempoPolicy` (coincide con modelo `IntentoTiempo`). NO requiere registro manual.
- **`form.transform()` NO es encadenable en Inertia React** — pero aquí no se usa transform (los intentos se arman en el handler antes de `form.post`). Usar `form.post(url, options)`.
- CRUD frontend: índice + `*-dialog.tsx` (`useForm`); errores `onError`→`toast` de sonner; Wayfinder default imports; `resources/js/actions`/`resources/js/routes` gitignored (regenerar `php artisan wayfinder:generate`). Nav por rol (`NavItem.roles`).
- Modelos: `IntentoTiempo` `#[Fillable(['id_inscripcion','numero_vuelta','tiempo_logrado','penalizacion_segundos'])]` (casts decimal:3 → strings), rel `inscripcion()`; `Inscripcion` rel `robot()`,`intentos()`,`inspecciones()`; `Robot` rel `categoria()`; `Categoria` (`tipo_evaluacion`). CHECK numero_vuelta 1-3 + UNIQUE(id_inscripcion,numero_vuelta); Trigger T3 (Aprobado requerido).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase. Factories: `User::factory()->juez()/coach()`, `['rol'=>...]`; `Categoria::factory()->tiempo()/combate()`; `Inscripcion::factory()->pagada()`; `InspeccionChecklist::factory()->aprobado()`; `Robot::factory()`.

---

## File Structure

**Backend:**
- Create: `app/Policies/IntentoTiempoPolicy.php`
- Create: `app/Http/Requests/GuardarTiemposRequest.php`
- Create: `app/Http/Controllers/TiempoController.php`
- Modify: `routes/web.php`

**Frontend:**
- Modify: `resources/js/types/models.ts` — `IntentoTiempoData`, `CompetidorTiempo`, `CategoriaTiempoOpcion`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Tiempos".
- Create: `resources/js/components/tiempos/capturar-tiempos-dialog.tsx`
- Create: `resources/js/pages/tiempos/index.tsx`

**Tests:** `tests/Feature/TiempoTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-4a
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-4a'`.

---

## Task 1: Backend de Tiempos (policy, request, controlador, rutas, tests)

**Files:**
- Create: `app/Policies/IntentoTiempoPolicy.php`
- Create: `app/Http/Requests/GuardarTiemposRequest.php`
- Create: `app/Http/Controllers/TiempoController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/tiempos/index.tsx` (placeholder mínimo; se reemplaza en Task 3)
- Test: `tests/Feature/TiempoTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/TiempoTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TiempoTest extends TestCase
{
    use RefreshDatabase;

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    /** Crea una inscripción aprobada de una categoría de tiempo y devuelve [inscripcion, categoria]. */
    private function inscripcionAprobadaTiempo(?Categoria $categoria = null): Inscripcion
    {
        $categoria ??= Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    public function test_todos_los_roles_ven_el_index(): void
    {
        foreach (['Administrador', 'Juez', 'Coach', 'Piloto'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get('/tiempos')
                ->assertOk();
        }
    }

    public function test_coach_y_piloto_no_pueden_capturar(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();
        $payload = [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
        ];

        $this->actingAs(User::factory()->coach()->create())->post('/tiempos', $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'Piloto']))->post('/tiempos', $payload)->assertForbidden();
    }

    public function test_juez_captura_tres_intentos(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [
                    ['numero_vuelta' => 1, 'tiempo_logrado' => 20.0, 'penalizacion_segundos' => 0],
                    ['numero_vuelta' => 2, 'tiempo_logrado' => 18.5, 'penalizacion_segundos' => 1.0],
                    ['numero_vuelta' => 3, 'tiempo_logrado' => 19.0, 'penalizacion_segundos' => 0],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(3, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
    }

    public function test_re_capturar_actualiza_por_vuelta(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();
        $juez = $this->juez();

        $post = fn (float $t) => $this->actingAs($juez)->post('/tiempos', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => $t, 'penalizacion_segundos' => 0]],
        ]);

        $post(20.0)->assertRedirect();
        $post(15.0)->assertRedirect();

        $this->assertSame(1, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
        $this->assertSame('15.000', IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->first()->tiempo_logrado);
    }

    public function test_no_se_captura_si_no_esta_aprobada(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('id_inscripcion');

        $this->assertSame(0, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
    }

    public function test_no_se_captura_si_categoria_es_combate(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('id_inscripcion');
    }

    public function test_ranking_ordena_por_mejor_tiempo(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $lento = $this->inscripcionAprobadaTiempo($categoria);
        $rapido = $this->inscripcionAprobadaTiempo($categoria);
        $sinTiempos = $this->inscripcionAprobadaTiempo($categoria);

        IntentoTiempo::create(['id_inscripcion' => $lento->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 20.0, 'penalizacion_segundos' => 0]);
        IntentoTiempo::create(['id_inscripcion' => $rapido->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 10.0, 'penalizacion_segundos' => 0]);

        $this->actingAs($this->juez())
            ->get('/tiempos?categoria='.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('tiempos/index')
                ->where('competidores.0.id_inscripcion', $rapido->id_inscripcion)
                ->where('competidores.0.posicion', 1)
                ->where('competidores.1.id_inscripcion', $lento->id_inscripcion)
                ->where('competidores.2.id_inscripcion', $sinTiempos->id_inscripcion)
                ->where('competidores.2.posicion', null)
            );
    }

    public function test_numero_vuelta_invalido_es_rechazado(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 4, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('intentos.0.numero_vuelta');
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=TiempoTest`
Expected: FAIL (rutas/controlador/policy inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/tiempos/index.tsx`:
```tsx
export default function TiemposIndex() {
    return <div>Tiempos</div>;
}
```

- [ ] **Step 4: Policy**

`app/Policies/IntentoTiempoPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\User;

class IntentoTiempoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function capturar(User $user): bool
    {
        return $user->isJuez();
    }
}
```

- [ ] **Step 5: Form Request**

`app/Http/Requests/GuardarTiemposRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarTiemposRequest extends FormRequest
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
            'id_inscripcion' => ['required', 'integer', 'exists:inscripciones,id_inscripcion'],
            'intentos' => ['required', 'array', 'max:3'],
            'intentos.*.numero_vuelta' => ['required', 'integer', 'in:1,2,3'],
            'intentos.*.tiempo_logrado' => ['nullable', 'numeric', 'gt:0'],
            'intentos.*.penalizacion_segundos' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 6: Controlador**

`app/Http/Controllers/TiempoController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTiemposRequest;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\IntentoTiempo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TiempoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IntentoTiempo::class);

        $categorias = Categoria::where('tipo_evaluacion', 'Tiempo')->orderBy('nombre')->get(['id_categoria', 'nombre']);
        $categoriaSeleccionada = (int) $request->query('categoria', (string) ($categorias->first()->id_categoria ?? 0));

        $competidores = collect();
        if ($categoriaSeleccionada > 0) {
            $inscripciones = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoriaSeleccionada))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->with(['robot', 'intentos'])
                ->get();

            $competidores = $inscripciones->map(function (Inscripcion $i) {
                $mejor = $i->intentos->isNotEmpty()
                    ? $i->intentos->min(fn (IntentoTiempo $t) => (float) $t->tiempo_logrado + (float) $t->penalizacion_segundos)
                    : null;

                return [
                    'id_inscripcion' => $i->id_inscripcion,
                    'robot' => $i->robot?->nombre,
                    'mejor_tiempo' => $mejor !== null ? number_format($mejor, 3, '.', '') : null,
                    'intentos' => $i->intentos->map(fn (IntentoTiempo $t) => [
                        'numero_vuelta' => $t->numero_vuelta,
                        'tiempo_logrado' => $t->tiempo_logrado,
                        'penalizacion_segundos' => $t->penalizacion_segundos,
                    ])->values(),
                ];
            })
                ->sortBy(fn (array $c) => $c['mejor_tiempo'] === null ? PHP_FLOAT_MAX : (float) $c['mejor_tiempo'])
                ->values();

            $posicion = 0;
            $competidores = $competidores->map(function (array $c) use (&$posicion) {
                if ($c['mejor_tiempo'] !== null) {
                    $posicion++;
                    $c['posicion'] = $posicion;
                } else {
                    $c['posicion'] = null;
                }

                return $c;
            });
        }

        return Inertia::render('tiempos/index', [
            'categorias' => $categorias,
            'categoriaSeleccionada' => $categoriaSeleccionada > 0 ? $categoriaSeleccionada : null,
            'competidores' => $competidores->values(),
            'puedeCapturar' => $request->user()->isJuez() || $request->user()->isAdministrador(),
        ]);
    }

    public function guardar(GuardarTiemposRequest $request): RedirectResponse
    {
        $this->authorize('capturar', IntentoTiempo::class);

        $data = $request->validated();
        $inscripcion = Inscripcion::with('robot.categoria')->findOrFail($data['id_inscripcion']);

        if ($inscripcion->robot?->categoria?->tipo_evaluacion !== 'Tiempo') {
            return back()->withErrors(['id_inscripcion' => 'La categoría no es de tiempo.']);
        }

        if (! $inscripcion->inspecciones()->where('estado_aprobacion', 'Aprobado')->exists()) {
            return back()->withErrors(['id_inscripcion' => 'El robot no está aprobado.']);
        }

        foreach ($data['intentos'] as $intento) {
            if (($intento['tiempo_logrado'] ?? null) === null) {
                continue;
            }

            IntentoTiempo::updateOrCreate(
                ['id_inscripcion' => $inscripcion->id_inscripcion, 'numero_vuelta' => $intento['numero_vuelta']],
                ['tiempo_logrado' => $intento['tiempo_logrado'], 'penalizacion_segundos' => $intento['penalizacion_segundos'] ?? 0],
            );
        }

        return back()->with('success', 'Tiempos registrados.');
    }
}
```

- [ ] **Step 7: Rutas**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\TiempoController;`.
- Dentro de un grupo `['auth','verified']`:
```php
    Route::get('tiempos', [TiempoController::class, 'index'])->name('tiempos.index');
    Route::post('tiempos', [TiempoController::class, 'guardar'])->name('tiempos.guardar');
```

- [ ] **Step 8: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=TiempoTest`
Expected: PASS (8 tests). (Si `assertInertia`/`assertOk` se queja del manifiesto Vite, correr `npm run build` una vez por la página placeholder.)

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/IntentoTiempoPolicy.php app/Http/Requests/GuardarTiemposRequest.php app/Http/Controllers/TiempoController.php routes/web.php resources/js/pages/tiempos/index.tsx tests/Feature/TiempoTest.php
git commit -m "feat(tiempos): backend de captura y ranking con guardas y updateOrCreate"
```

---

## Task 2: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/TiempoController.ts` (index/guardar) y `resources/js/routes/tiempos/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type IntentoTiempoData = {
    numero_vuelta: number;
    tiempo_logrado: string;
    penalizacion_segundos: string;
};

export type CompetidorTiempo = {
    id_inscripcion: number;
    robot: string | null;
    posicion: number | null;
    mejor_tiempo: string | null;
    intentos: IntentoTiempoData[];
};

export type CategoriaTiempoOpcion = {
    id_categoria: number;
    nombre: string;
};
```

- [ ] **Step 3: Ítem de navegación "Tiempos"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `Timer` a los iconos importados de `lucide-react`.
- Añadir `import tiempos from '@/routes/tiempos';`.
- Añadir al array `mainNavItems` (después de "Inspección"):
```tsx
    {
        title: 'Tiempos',
        href: tiempos.index(),
        icon: Timer,
        roles: ['Administrador', 'Juez', 'Coach', 'Piloto'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(tiempos): tipos frontend y navegacion"
```

---

## Task 3: UI de Tiempos (modal de captura + índice con ranking)

**Files:**
- Create: `resources/js/components/tiempos/capturar-tiempos-dialog.tsx`
- Modify: `resources/js/pages/tiempos/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Modal de captura**

`resources/js/components/tiempos/capturar-tiempos-dialog.tsx`:
```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import TiempoController from '@/actions/App/Http/Controllers/TiempoController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CompetidorTiempo } from '@/types';

type Fila = { tiempo_logrado: string; penalizacion_segundos: string };

type Props = {
    competidor: CompetidorTiempo;
    trigger: React.ReactNode;
};

const VUELTAS = [1, 2, 3] as const;

function filaInicial(competidor: CompetidorTiempo, vuelta: number): Fila {
    const intento = competidor.intentos.find((i) => i.numero_vuelta === vuelta);
    return {
        tiempo_logrado: intento?.tiempo_logrado ?? '',
        penalizacion_segundos: intento?.penalizacion_segundos ?? '',
    };
}

export default function CapturarTiemposDialog({ competidor, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ id_inscripcion: number; filas: Record<number, Fila> }>({
        id_inscripcion: competidor.id_inscripcion,
        filas: {
            1: filaInicial(competidor, 1),
            2: filaInicial(competidor, 2),
            3: filaInicial(competidor, 3),
        },
    });

    const setFila = (vuelta: number, campo: keyof Fila, valor: string) => {
        form.setData('filas', {
            ...form.data.filas,
            [vuelta]: { ...form.data.filas[vuelta], [campo]: valor },
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            id_inscripcion: data.id_inscripcion,
            intentos: VUELTAS.map((v) => ({
                numero_vuelta: v,
                tiempo_logrado: data.filas[v].tiempo_logrado === '' ? null : data.filas[v].tiempo_logrado,
                penalizacion_segundos: data.filas[v].penalizacion_segundos === '' ? 0 : data.filas[v].penalizacion_segundos,
            })),
        }));
        form.post(TiempoController.guardar.url(), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors) => {
                const message = Object.values(errors)[0];
                if (message) {
                    toast.error(message);
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Capturar tiempos · {competidor.robot}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {VUELTAS.map((v) => (
                        <div key={v} className="grid grid-cols-[auto_1fr_1fr] items-end gap-3">
                            <span className="pb-2 text-sm font-medium">Vuelta {v}</span>
                            <div className="grid gap-1">
                                <Label htmlFor={`tiempo-${v}`}>Tiempo (s)</Label>
                                <Input
                                    id={`tiempo-${v}`}
                                    type="number"
                                    step="0.001"
                                    value={form.data.filas[v].tiempo_logrado}
                                    onChange={(e) => setFila(v, 'tiempo_logrado', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor={`penal-${v}`}>Penalización (s)</Label>
                                <Input
                                    id={`penal-${v}`}
                                    type="number"
                                    step="0.001"
                                    value={form.data.filas[v].penalizacion_segundos}
                                    onChange={(e) => setFila(v, 'penalizacion_segundos', e.target.value)}
                                />
                            </div>
                        </div>
                    ))}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Guardar tiempos
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```
(Nota: `form.transform(...)` y `form.post(...)` se llaman como DOS sentencias separadas — `transform` no es encadenable en Inertia React.)

- [ ] **Step 2: Página índice**

Reemplazar `resources/js/pages/tiempos/index.tsx` por:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import CapturarTiemposDialog from '@/components/tiempos/capturar-tiempos-dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import tiempos from '@/routes/tiempos';
import type { CategoriaTiempoOpcion, CompetidorTiempo, IntentoTiempoData } from '@/types';

type PageProps = {
    categorias: CategoriaTiempoOpcion[];
    categoriaSeleccionada: number | null;
    competidores: CompetidorTiempo[];
    puedeCapturar: boolean;
};

function celdaVuelta(intentos: IntentoTiempoData[], vuelta: number): string {
    const intento = intentos.find((i) => i.numero_vuelta === vuelta);
    if (!intento) {
        return '—';
    }
    const penal = Number(intento.penalizacion_segundos);
    return penal > 0 ? `${intento.tiempo_logrado} (+${intento.penalizacion_segundos})` : intento.tiempo_logrado;
}

export default function TiemposIndex() {
    const { categorias, categoriaSeleccionada, competidores, puedeCapturar } = usePage<PageProps>().props;

    const cambiarCategoria = (id: string) => {
        router.get(tiempos.index().url, { categoria: id }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Tiempos" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Tiempos y posiciones</h1>
                    {categorias.length > 0 && (
                        <Select value={categoriaSeleccionada ? String(categoriaSeleccionada) : undefined} onValueChange={cambiarCategoria}>
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
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">#</th>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">V1</th>
                                <th scope="col" className="p-3">V2</th>
                                <th scope="col" className="p-3">V3</th>
                                <th scope="col" className="p-3">Mejor</th>
                                {puedeCapturar && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {competidores.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={puedeCapturar ? 7 : 6}>
                                        No hay competidores aprobados en esta categoría.
                                    </td>
                                </tr>
                            ) : (
                                competidores.map((c) => (
                                    <tr key={c.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{c.posicion ?? '—'}</td>
                                        <td className="p-3">{c.robot ?? '—'}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 1)}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 2)}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 3)}</td>
                                        <td className="p-3 font-semibold">{c.mejor_tiempo ?? '—'}</td>
                                        {puedeCapturar && (
                                            <td className="p-3">
                                                <div className="flex justify-end">
                                                    <CapturarTiemposDialog
                                                        competidor={c}
                                                        trigger={<Button variant="secondary" size="sm">Capturar</Button>}
                                                    />
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

TiemposIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tiempos',
            href: tiempos.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/tiempos resources/js/pages/tiempos
git commit -m "feat(tiempos): UI de captura y tabla de posiciones"
```

---

## Task 4: Verificación integral de la Fase 2.4a

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (110 previos + TiempoTest 8 = 118).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(tiempos): verificacion integral Fase 2.4a" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `IntentoTiempoPolicy` (admin before; viewAny todos; capturar juez) → Task 1 ✓
- `TiempoController` index (selector categoría, ranking PHP, posiciones, nulls al final) + guardar (guardas categoría-Tiempo y Aprobado, updateOrCreate por vuelta) → Task 1 ✓
- `GuardarTiemposRequest` (intentos array max3, numero_vuelta in 1-3, tiempo gt0, penal min0) → Task 1 ✓
- Rutas index/guardar → Task 1 ✓
- Tipos + Wayfinder + nav "Tiempos" (4 roles) → Task 2 ✓
- UI: selector de categoría, tabla captura+ranking, modal de 3 vueltas con prefill → Task 3 ✓
- Pruebas: auth (todos ven, coach/piloto 403 capturar), captura 3, re-captura por vuelta, no-aprobada, combate, ranking+posiciones, vuelta inválida → Task 1 ✓
- DoD: suite 100%, pint, build → Task 4 ✓

**Riesgo conocido (Wayfinder):** `@/routes/tiempos` y `@/actions/.../TiempoController` existen tras `php artisan wayfinder:generate` (Task 2 Step 1). `guardar.url()` no lleva id. Si `assertInertia` falla en Task 1 por manifiesto Vite, correr `npm run build` una vez.
