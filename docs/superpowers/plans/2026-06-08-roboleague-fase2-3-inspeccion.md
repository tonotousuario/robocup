# RoboLeague Fase 2.3 — Inspección técnica · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar la inspección técnica (peso/dimensiones + veredicto Aprobado/Rechazado/Descalificado) de inscripciones pagadas, con Juez/Admin inspeccionan y Piloto ve lo suyo.

**Architecture:** `InspeccionPolicy` (auto-descubierta) + `InspeccionController` (trait `AuthorizesRequests`) con index escopado por rol y `guardar` (updateOrCreate por id_inscripcion) protegido por guard de "Pagado" (trigger T1 como candado final). Frontend Inertia/React: índice con tabla (acciones solo Juez/Admin) + modal de inspección con referencia de límites de categoría, vía Wayfinder.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Controladores extienden `App\Http\Controllers\Controller` + `use AuthorizesRequests;` + `$this->authorize(...)` explícito (base Controller plano; ver `InscripcionController`/`RobotController`).
- Policies auto-descubiertas (`InspeccionChecklist`→`InspeccionPolicy`).
- CRUD frontend: índice + `*-dialog.tsx` (`useForm`); errores `onError`→`toast` de sonner; Wayfinder default imports; `resources/js/actions` y `resources/js/routes` gitignored (regenerar `php artisan wayfinder:generate`). Nav por rol (`NavItem.roles`). Badge de estado con mapa de clases (ver `inscripciones/index.tsx`).
- Modelos: `InspeccionChecklist` `#[Fillable(['id_inscripcion','id_juez','peso_medido_g','dimensiones_medidas','estado_aprobacion','observaciones'])]`, rel `inscripcion()`,`juez()`; `Inscripcion` rel `robot()`,`inspecciones()` (hasMany); `Robot` rel `categoria()`,`piloto()`; `Categoria` (`peso_maximo_g`,`dimensiones_maximas`).
- Trigger T1 (BD): inspección requiere inscripción Pagada (candado final).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase contra `roboleague_testing`. Factories: `User::factory()->juez()/coach()`, `['rol'=>'Piloto'|'Administrador']`; `Inscripcion::factory()` (default Pendiente, `pagada()` state); `InspeccionChecklist::factory()` (`aprobado()` state, inscripcion via `Inscripcion::factory()->pagada()`); `Robot::factory()`.

---

## File Structure

**Backend:**
- Create: `app/Policies/InspeccionPolicy.php`
- Create: `app/Http/Requests/GuardarInspeccionRequest.php`
- Create: `app/Http/Controllers/InspeccionController.php`
- Modify: `routes/web.php`

**Frontend:**
- Modify: `resources/js/types/models.ts` — `InspeccionEstado`, `InspeccionRow` (alias distinto al de inscripciones — ver nota).
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Inspección".
- Create: `resources/js/components/inspecciones/inspeccionar-dialog.tsx`
- Create: `resources/js/pages/inspecciones/index.tsx`

**Tests:** `tests/Feature/InspeccionTest.php`.

> Nota de nombres: ya existe `InscripcionRow` (inscripciones). Para inspección usar `InspeccionListItem` para evitar colisión.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-3
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-3'`.

---

## Task 1: Backend de Inspección (policy, request, controlador, rutas, tests)

**Files:**
- Create: `app/Policies/InspeccionPolicy.php`
- Create: `app/Http/Requests/GuardarInspeccionRequest.php`
- Create: `app/Http/Controllers/InspeccionController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/inspecciones/index.tsx` (placeholder mínimo; se reemplaza en Task 3)
- Test: `tests/Feature/InspeccionTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/InspeccionTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InspeccionTest extends TestCase
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

    public function test_coach_recibe_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())->get('/inspecciones')->assertForbidden();
    }

    public function test_juez_ve_inscripciones_pagadas(): void
    {
        Inscripcion::factory()->pagada()->create();
        Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);

        $this->actingAs($this->juez())
            ->get('/inspecciones')
            ->assertInertia(fn (Assert $page) => $page->component('inspecciones/index')->has('inspecciones', 1));
    }

    public function test_piloto_solo_ve_sus_inscripciones(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        $miRobot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        Inscripcion::factory()->pagada()->create(['id_robot' => $miRobot->id_robot]);
        Inscripcion::factory()->pagada()->create();

        $this->actingAs($piloto)
            ->get('/inspecciones')
            ->assertInertia(fn (Assert $page) => $page->component('inspecciones/index')->has('inspecciones', 1));
    }

    public function test_juez_inspecciona_inscripcion_pagada(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
                'observaciones' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inspecciones_checklist', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'id_juez' => $juez->id,
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_re_inspeccionar_actualiza_la_misma_fila(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $payload = fn (string $estado) => [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'peso_medido_g' => 480,
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => $estado,
            'observaciones' => null,
        ];

        $this->actingAs($juez)->post('/inspecciones', $payload('Rechazado'))->assertRedirect();
        $this->actingAs($juez)->post('/inspecciones', $payload('Aprobado'))->assertRedirect();

        $this->assertSame(1, InspeccionChecklist::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
        $this->assertDatabaseHas('inspecciones_checklist', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_no_se_inspecciona_inscripcion_no_pagada(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
            ])
            ->assertSessionHasErrors('id_inscripcion');

        $this->assertDatabaseMissing('inspecciones_checklist', ['id_inscripcion' => $inscripcion->id_inscripcion]);
    }

    public function test_piloto_no_puede_guardar(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);

        $this->actingAs($piloto)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Aprobado',
            ])
            ->assertForbidden();
    }

    public function test_estado_invalido_es_rechazado(): void
    {
        $juez = $this->juez();
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->actingAs($juez)
            ->post('/inspecciones', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'peso_medido_g' => 480,
                'dimensiones_medidas' => '19x19 cm',
                'estado_aprobacion' => 'Pendiente',
            ])
            ->assertSessionHasErrors('estado_aprobacion');
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=InspeccionTest`
Expected: FAIL (rutas/controlador/policy inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/inspecciones/index.tsx`:
```tsx
export default function InspeccionesIndex() {
    return <div>Inspección</div>;
}
```

- [ ] **Step 4: Policy**

`app/Policies/InspeccionPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\User;

class InspeccionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isJuez() || $user->isPiloto();
    }

    public function guardar(User $user): bool
    {
        return $user->isJuez();
    }
}
```

- [ ] **Step 5: Form Request**

`app/Http/Requests/GuardarInspeccionRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarInspeccionRequest extends FormRequest
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
            'peso_medido_g' => ['required', 'integer', 'min:0'],
            'dimensiones_medidas' => ['required', 'string', 'max:255'],
            'estado_aprobacion' => ['required', 'in:Aprobado,Rechazado,Descalificado'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 6: Controlador**

`app/Http/Controllers/InspeccionController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarInspeccionRequest;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InspeccionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InspeccionChecklist::class);

        $user = $request->user();

        $query = Inscripcion::with(['robot.categoria', 'robot.piloto', 'inspecciones'])->orderBy('id_inscripcion');

        if ($user->isPiloto()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        } else {
            $query->where('estado_pago', 'Pagado');
        }

        $inscripciones = $query->get()->map(function (Inscripcion $i) {
            $inspeccion = $i->inspecciones->first();

            return [
                'id_inscripcion' => $i->id_inscripcion,
                'robot' => $i->robot?->nombre,
                'categoria' => $i->robot?->categoria?->nombre,
                'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
                'peso_maximo_g' => $i->robot?->categoria?->peso_maximo_g,
                'dimensiones_maximas' => $i->robot?->categoria?->dimensiones_maximas,
                'estado_pago' => $i->estado_pago,
                'estado' => $inspeccion?->estado_aprobacion ?? 'Pendiente',
                'inspeccion' => $inspeccion ? [
                    'peso_medido_g' => $inspeccion->peso_medido_g,
                    'dimensiones_medidas' => $inspeccion->dimensiones_medidas,
                    'estado_aprobacion' => $inspeccion->estado_aprobacion,
                    'observaciones' => $inspeccion->observaciones,
                ] : null,
            ];
        })->values();

        return Inertia::render('inspecciones/index', [
            'inspecciones' => $inscripciones,
            'puedeInspeccionar' => $user->isJuez() || $user->isAdministrador(),
        ]);
    }

    public function guardar(GuardarInspeccionRequest $request): RedirectResponse
    {
        $this->authorize('guardar', InspeccionChecklist::class);

        $data = $request->validated();
        $inscripcion = Inscripcion::findOrFail($data['id_inscripcion']);

        if ($inscripcion->estado_pago !== 'Pagado') {
            return back()->withErrors(['id_inscripcion' => 'La inscripción no está pagada; no puede inspeccionarse.']);
        }

        InspeccionChecklist::updateOrCreate(
            ['id_inscripcion' => $inscripcion->id_inscripcion],
            [
                'id_juez' => $request->user()->id,
                'peso_medido_g' => $data['peso_medido_g'],
                'dimensiones_medidas' => $data['dimensiones_medidas'],
                'estado_aprobacion' => $data['estado_aprobacion'],
                'observaciones' => $data['observaciones'] ?? null,
            ],
        );

        return back()->with('success', 'Inspección registrada.');
    }
}
```

- [ ] **Step 7: Rutas**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\InspeccionController;`.
- Dentro de un grupo `['auth','verified']`:
```php
    Route::get('inspecciones', [InspeccionController::class, 'index'])->name('inspecciones.index');
    Route::post('inspecciones', [InspeccionController::class, 'guardar'])->name('inspecciones.guardar');
```

- [ ] **Step 8: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=InspeccionTest`
Expected: PASS (8 tests). (Si `assertInertia` se queja de manifiesto Vite, ejecutar `npm run build` una vez para registrar la página placeholder.)

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/InspeccionPolicy.php app/Http/Requests/GuardarInspeccionRequest.php app/Http/Controllers/InspeccionController.php routes/web.php resources/js/pages/inspecciones/index.tsx tests/Feature/InspeccionTest.php
git commit -m "feat(inspeccion): backend de checklist con guard de pago y updateOrCreate"
```

---

## Task 2: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/InspeccionController.ts` (index/guardar) y `resources/js/routes/inspecciones/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type InspeccionEstado = 'Pendiente' | 'Aprobado' | 'Rechazado' | 'Descalificado';

export type InspeccionListItem = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    piloto: string | null;
    peso_maximo_g: number | null;
    dimensiones_maximas: string | null;
    estado_pago: string;
    estado: InspeccionEstado;
    inspeccion: {
        peso_medido_g: number;
        dimensiones_medidas: string;
        estado_aprobacion: string;
        observaciones: string | null;
    } | null;
};
```

- [ ] **Step 3: Ítem de navegación "Inspección"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `ClipboardCheck` a los iconos importados de `lucide-react`.
- Añadir `import inspecciones from '@/routes/inspecciones';`.
- Añadir al array `mainNavItems` (después de "Inscripciones"):
```tsx
    {
        title: 'Inspección',
        href: inspecciones.index(),
        icon: ClipboardCheck,
        roles: ['Administrador', 'Juez', 'Piloto'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(inspeccion): tipos frontend y navegacion"
```

---

## Task 3: UI de Inspección (modal + índice)

**Files:**
- Create: `resources/js/components/inspecciones/inspeccionar-dialog.tsx`
- Modify: `resources/js/pages/inspecciones/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Modal de inspección**

`resources/js/components/inspecciones/inspeccionar-dialog.tsx`:
```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InspeccionController from '@/actions/App/Http/Controllers/InspeccionController';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { InspeccionListItem } from '@/types';

const ESTADOS = ['Aprobado', 'Rechazado', 'Descalificado'] as const;

type Props = {
    item: InspeccionListItem;
    trigger: React.ReactNode;
};

export default function InspeccionarDialog({ item, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        id_inscripcion: item.id_inscripcion,
        peso_medido_g: item.inspeccion ? String(item.inspeccion.peso_medido_g) : '',
        dimensiones_medidas: item.inspeccion?.dimensiones_medidas ?? '',
        estado_aprobacion: item.inspeccion?.estado_aprobacion ?? 'Aprobado',
        observaciones: item.inspeccion?.observaciones ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(InspeccionController.guardar.url(), {
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
                    <DialogTitle>Inspeccionar {item.robot}</DialogTitle>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    Límites: {item.peso_maximo_g ?? '—'} g · {item.dimensiones_maximas ?? '—'}
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="peso_medido_g">Peso medido (g)</Label>
                        <Input id="peso_medido_g" type="number" value={form.data.peso_medido_g} onChange={(e) => form.setData('peso_medido_g', e.target.value)} />
                        <InputError message={form.errors.peso_medido_g} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="dimensiones_medidas">Dimensiones medidas</Label>
                        <Input id="dimensiones_medidas" value={form.data.dimensiones_medidas} onChange={(e) => form.setData('dimensiones_medidas', e.target.value)} />
                        <InputError message={form.errors.dimensiones_medidas} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="estado_aprobacion">Veredicto</Label>
                        <Select value={form.data.estado_aprobacion} onValueChange={(v) => form.setData('estado_aprobacion', v)}>
                            <SelectTrigger id="estado_aprobacion">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ESTADOS.map((est) => (
                                    <SelectItem key={est} value={est}>
                                        {est}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.estado_aprobacion} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="observaciones">Observaciones</Label>
                        <Input id="observaciones" value={form.data.observaciones} onChange={(e) => form.setData('observaciones', e.target.value)} />
                        <InputError message={form.errors.observaciones} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Guardar inspección
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Página índice**

Reemplazar `resources/js/pages/inspecciones/index.tsx` por:
```tsx
import { Head, usePage } from '@inertiajs/react';
import InspeccionarDialog from '@/components/inspecciones/inspeccionar-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import inspecciones from '@/routes/inspecciones';
import type { InspeccionEstado, InspeccionListItem } from '@/types';

type PageProps = {
    inspecciones: InspeccionListItem[];
    puedeInspeccionar: boolean;
};

const ESTADO_CLASS: Record<InspeccionEstado, string> = {
    Pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Aprobado: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Rechazado: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    Descalificado: 'bg-neutral-300 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200',
};

export default function InspeccionesIndex() {
    const { inspecciones: rows, puedeInspeccionar } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Inspección" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Inspección técnica</h1>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">Categoría</th>
                                {puedeInspeccionar && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3">Estado</th>
                                {puedeInspeccionar && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={puedeInspeccionar ? 5 : 3}>
                                        No hay inscripciones para inspeccionar.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((item) => (
                                    <tr key={item.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{item.robot ?? '—'}</td>
                                        <td className="p-3">{item.categoria ?? '—'}</td>
                                        {puedeInspeccionar && <td className="p-3">{item.piloto ?? '—'}</td>}
                                        <td className="p-3">
                                            <Badge variant="secondary" className={ESTADO_CLASS[item.estado]}>
                                                {item.estado}
                                            </Badge>
                                        </td>
                                        {puedeInspeccionar && (
                                            <td className="p-3">
                                                <div className="flex justify-end">
                                                    <InspeccionarDialog
                                                        item={item}
                                                        trigger={
                                                            <Button variant="secondary" size="sm">
                                                                {item.inspeccion ? 'Re-inspeccionar' : 'Inspeccionar'}
                                                            </Button>
                                                        }
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

InspeccionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inspección',
            href: inspecciones.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/inspecciones resources/js/pages/inspecciones
git commit -m "feat(inspeccion): UI de checklist con modal y referencia de limites"
```

---

## Task 4: Verificación integral de la Fase 2.3

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (102 previos + InspeccionTest 8 = 110).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(inspeccion): verificacion integral Fase 2.3" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `InspeccionPolicy` (admin before; viewAny juez/piloto; guardar juez) → Task 1 ✓
- `InspeccionController`: index escopado (Juez/Admin → Pagadas; Piloto → sus robots) + límites de categoría + inspección actual/estado; guardar con guard de pago + updateOrCreate + id_juez → Task 1 ✓
- Guard no-pagada (UX) con trigger T1 como candado final → Task 1 ✓
- `GuardarInspeccionRequest` (estado in 3 veredictos, peso int, etc.) → Task 1 ✓
- Rutas index/guardar → Task 1 ✓
- Tipos + Wayfinder + nav "Inspección" (Admin/Juez/Piloto) → Task 2 ✓
- UI: modal con referencia de límites + prefill + select veredicto; tabla con badge; acciones solo si `puedeInspeccionar`; Piloto lectura → Task 3 ✓
- Pruebas: auth (coach 403, piloto 403 guardar), scope piloto, inspeccionar, re-inspeccionar (1 fila), no-pagada, estado inválido → Task 1 ✓
- DoD: suite 100%, pint, build → Task 4 ✓

**Riesgo conocido (Wayfinder):** `@/routes/inspecciones` y `@/actions/.../InspeccionController` existen tras `php artisan wayfinder:generate` (Task 2 Step 1). `guardar.url()` no lleva parámetro (no es por-id). Si `assertInertia` falla en Task 1 por manifiesto Vite, correr `npm run build` una vez (la página placeholder debe estar en el manifiesto).
