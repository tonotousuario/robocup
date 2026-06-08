# RoboLeague Fase 2.2 — Inscripciones / Financiero · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Inscribir robots con cálculo automático de tarifa por fecha y gestión de caja (Pendiente/Pagado/Cancelado), con Piloto-inscribe / Admin-cobra.

**Architecture:** `TarifaService` resuelve la tarifa vigente por fecha (unidad aislada). `InscripcionPolicy` + `InscripcionController` (trait `AuthorizesRequests`, como `RobotController`) gestionan inscripción (Piloto sobre sus robots) y acciones de caja `pagar`/`cancelar` (solo Admin). Frontend Inertia/React: índice con tabla + modal de inscripción + acciones, vía Wayfinder. Reusa `ConfirmDeleteDialog`, `toast`, patrón 2.1.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Modelos `#[Fillable([...])]`; controladores extienden el `Controller` base (plano) y usan el trait `Illuminate\Foundation\Auth\Access\AuthorizesRequests` con `$this->authorize(...)` explícito (NO `authorizeResource`, no disponible aquí — ver `RobotController`).
- Policies se auto-descubren (`Inscripcion`→`InscripcionPolicy`).
- CRUD frontend: índice + `*-dialog.tsx` (`useForm`) + `ConfirmDeleteDialog` (`@/components/confirm-delete-dialog`); errores `onError`→`toast` de `sonner`; Wayfinder default imports; `resources/js/actions` y `resources/js/routes` gitignored (regenerar con `php artisan wayfinder:generate`).
- Nav por rol en `app-sidebar.tsx` (`NavItem.roles`).
- `Inscripcion` `#[Fillable(['id_robot','id_tarifa','monto_pagado','estado_pago'])]`; `estado_pago` CHECK {Pendiente,Pagado,Cancelado}; relaciones `robot()`,`tarifa()`. `Robot` rel `inscripciones()`. `Tarifa` (`id_tarifa`,`descripcion`,`fecha_inicio_cobro`,`fecha_fin_cobro`,`monto`).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. BD PostgreSQL, RefreshDatabase contra `roboleague_testing`. Factories: `User::factory()->juez()/coach()`, `['rol'=>'Piloto'|'Administrador']`; `Robot::factory()`, `Tarifa::factory()`, `Inscripcion::factory()` (estado pago default 'Pendiente', `pagada()` state).

---

## File Structure

**Backend:**
- Create: `app/Services/TarifaService.php`
- Create: `app/Policies/InscripcionPolicy.php`
- Create: `app/Http/Requests/StoreInscripcionRequest.php`
- Create: `app/Http/Controllers/InscripcionController.php`
- Modify: `routes/web.php`

**Frontend:**
- Modify: `resources/js/types/models.ts` — `InscripcionRow`, `RobotInscribible`, `TarifaVigente`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Inscripciones".
- Create: `resources/js/components/inscripciones/inscribir-robot-dialog.tsx`
- Create: `resources/js/pages/inscripciones/index.tsx`

**Tests:** `tests/Unit/TarifaServiceTest.php`, `tests/Feature/InscripcionTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-2
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-2'`.

---

## Task 1: `TarifaService` (cálculo de tarifa vigente por fecha)

**Files:**
- Create: `app/Services/TarifaService.php`
- Test: `tests/Unit/TarifaServiceTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Unit/TarifaServiceTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Tarifa;
use App\Services\TarifaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarifaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_la_tarifa_cuyo_rango_contiene_la_fecha(): void
    {
        Tarifa::factory()->create(['descripcion' => 'Preventa', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31', 'monto' => 150]);
        $regular = Tarifa::factory()->create(['descripcion' => 'Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250]);

        $tarifa = (new TarifaService())->vigentePara(Carbon::parse('2026-04-15'));

        $this->assertNotNull($tarifa);
        $this->assertSame($regular->id_tarifa, $tarifa->id_tarifa);
    }

    public function test_devuelve_null_si_ninguna_tarifa_cubre_la_fecha(): void
    {
        Tarifa::factory()->create(['fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31']);

        $tarifa = (new TarifaService())->vigentePara(Carbon::parse('2026-07-01'));

        $this->assertNull($tarifa);
    }

    public function test_vigente_para_hoy_usa_la_fecha_actual(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));
        $regular = Tarifa::factory()->create(['fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31']);

        $tarifa = (new TarifaService())->vigenteParaHoy();

        $this->assertNotNull($tarifa);
        $this->assertSame($regular->id_tarifa, $tarifa->id_tarifa);
        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=TarifaServiceTest`
Expected: FAIL (`Class "App\Services\TarifaService" not found`).

- [ ] **Step 3: Implementar el servicio**

`app/Services/TarifaService.php`:
```php
<?php

namespace App\Services;

use App\Models\Tarifa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TarifaService
{
    public function vigenteParaHoy(): ?Tarifa
    {
        return $this->vigentePara(Carbon::now());
    }

    public function vigentePara(CarbonInterface $fecha): ?Tarifa
    {
        return Tarifa::whereDate('fecha_inicio_cobro', '<=', $fecha)
            ->whereDate('fecha_fin_cobro', '>=', $fecha)
            ->orderBy('fecha_inicio_cobro')
            ->first();
    }
}
```

- [ ] **Step 4: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=TarifaServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/TarifaService.php tests/Unit/TarifaServiceTest.php
git commit -m "feat(tarifas): servicio de tarifa vigente por fecha"
```

---

## Task 2: Backend de Inscripciones (policy, request, controlador, rutas, tests)

**Files:**
- Create: `app/Policies/InscripcionPolicy.php`
- Create: `app/Http/Requests/StoreInscripcionRequest.php`
- Create: `app/Http/Controllers/InscripcionController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/inscripciones/index.tsx` (placeholder mínimo; se reemplaza en Task 4)
- Test: `tests/Feature/InscripcionTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/InscripcionTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\Robot;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15'));
        Tarifa::factory()->create(['descripcion' => 'Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    private function piloto(): User
    {
        return User::factory()->create(['rol' => 'Piloto']);
    }

    public function test_juez_y_coach_reciben_403(): void
    {
        $this->actingAs(User::factory()->juez()->create())->get('/inscripciones')->assertForbidden();
        $this->actingAs(User::factory()->coach()->create())->get('/inscripciones')->assertForbidden();
    }

    public function test_piloto_solo_ve_sus_inscripciones(): void
    {
        $piloto = $this->piloto();
        $miRobot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        Inscripcion::factory()->create(['id_robot' => $miRobot->id_robot]);
        Inscripcion::factory()->create();

        $this->actingAs($piloto)
            ->get('/inscripciones')
            ->assertInertia(fn (Assert $page) => $page->component('inscripciones/index')->has('inscripciones', 1));
    }

    public function test_piloto_inscribe_su_robot_con_tarifa_vigente(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertRedirect();

        $this->assertDatabaseHas('inscripciones', [
            'id_robot' => $robot->id_robot,
            'estado_pago' => 'Pendiente',
            'monto_pagado' => 0,
        ]);
        $this->assertDatabaseHas('inscripciones', ['id_robot' => $robot->id_robot]);
        $inscripcion = Inscripcion::where('id_robot', $robot->id_robot)->first();
        $this->assertNotNull($inscripcion->id_tarifa);
    }

    public function test_piloto_no_puede_inscribir_robot_ajeno(): void
    {
        $robotAjeno = Robot::factory()->create();

        $this->actingAs($this->piloto())
            ->post('/inscripciones', ['id_robot' => $robotAjeno->id_robot])
            ->assertSessionHasErrors('id_robot');

        $this->assertDatabaseMissing('inscripciones', ['id_robot' => $robotAjeno->id_robot]);
    }

    public function test_sin_tarifa_vigente_se_bloquea(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01')); // fuera del rango de la tarifa
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertSessionHasErrors('id_robot');

        $this->assertDatabaseMissing('inscripciones', ['id_robot' => $robot->id_robot]);
    }

    public function test_duplicado_se_bloquea_y_re_inscripcion_tras_cancelar(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'estado_pago' => 'Pendiente']);

        // duplicado bloqueado
        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertSessionHasErrors('id_robot');
        $this->assertSame(1, Inscripcion::where('id_robot', $robot->id_robot)->count());

        // cancelar libera el robot
        $this->actingAs($this->admin())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/cancelar")
            ->assertRedirect();

        $this->actingAs($piloto)
            ->post('/inscripciones', ['id_robot' => $robot->id_robot])
            ->assertRedirect();
        $this->assertSame(2, Inscripcion::where('id_robot', $robot->id_robot)->count());
    }

    public function test_admin_marca_pagado_con_monto_de_tarifa(): void
    {
        $robot = Robot::factory()->create();
        $tarifa = Tarifa::factory()->create(['monto' => 400, 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31']);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'id_tarifa' => $tarifa->id_tarifa, 'estado_pago' => 'Pendiente', 'monto_pagado' => 0]);

        $this->actingAs($this->admin())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/pagar")
            ->assertRedirect();

        $this->assertDatabaseHas('inscripciones', ['id_inscripcion' => $inscripcion->id_inscripcion, 'estado_pago' => 'Pagado', 'monto_pagado' => 400]);
    }

    public function test_piloto_no_puede_pagar_ni_cancelar(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'estado_pago' => 'Pendiente']);

        $this->actingAs($piloto)->patch("/inscripciones/{$inscripcion->id_inscripcion}/pagar")->assertForbidden();
        $this->actingAs($piloto)->patch("/inscripciones/{$inscripcion->id_inscripcion}/cancelar")->assertForbidden();
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=InscripcionTest`
Expected: FAIL (rutas/controlador/policy inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/inscripciones/index.tsx`:
```tsx
export default function InscripcionesIndex() {
    return <div>Inscripciones</div>;
}
```

- [ ] **Step 4: Policy**

`app/Policies/InscripcionPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\User;

class InscripcionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isPiloto();
    }

    public function create(User $user): bool
    {
        return $user->isPiloto();
    }

    public function pagar(User $user, Inscripcion $inscripcion): bool
    {
        return false;
    }

    public function cancelar(User $user, Inscripcion $inscripcion): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Form Request**

`app/Http/Requests/StoreInscripcionRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInscripcionRequest extends FormRequest
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
            'id_robot' => ['required', 'integer', 'exists:robots,id_robot'],
        ];
    }
}
```

- [ ] **Step 6: Controlador**

`app/Http/Controllers/InscripcionController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Models\Inscripcion;
use App\Models\Robot;
use App\Services\TarifaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InscripcionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private TarifaService $tarifas) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inscripcion::class);

        $user = $request->user();

        $query = Inscripcion::with(['robot.piloto', 'robot.categoria', 'tarifa'])->orderByDesc('id_inscripcion');

        if (! $user->isAdministrador()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        }

        $inscripciones = $query->get()->map(fn (Inscripcion $i) => [
            'id_inscripcion' => $i->id_inscripcion,
            'robot' => $i->robot?->nombre,
            'categoria' => $i->robot?->categoria?->nombre,
            'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
            'tarifa' => $i->tarifa?->descripcion,
            'monto_pagado' => $i->monto_pagado,
            'estado_pago' => $i->estado_pago,
        ])->values();

        $robotsQuery = Robot::whereDoesntHave('inscripciones', fn ($q) => $q->where('estado_pago', '!=', 'Cancelado'))->orderBy('nombre');
        if (! $user->isAdministrador()) {
            $robotsQuery->where('id_piloto', $user->id);
        }

        $tarifaVigente = $this->tarifas->vigenteParaHoy();

        return Inertia::render('inscripciones/index', [
            'inscripciones' => $inscripciones,
            'robotsInscribibles' => $robotsQuery->get(['id_robot', 'nombre']),
            'tarifaVigente' => $tarifaVigente ? ['descripcion' => $tarifaVigente->descripcion, 'monto' => $tarifaVigente->monto] : null,
        ]);
    }

    public function store(StoreInscripcionRequest $request): RedirectResponse
    {
        $this->authorize('create', Inscripcion::class);

        $robot = Robot::findOrFail($request->integer('id_robot'));
        $user = $request->user();

        if (! $user->isAdministrador() && (int) $robot->id_piloto !== $user->id) {
            return back()->withErrors(['id_robot' => 'Ese robot no te pertenece.']);
        }

        if ($robot->inscripciones()->where('estado_pago', '!=', 'Cancelado')->exists()) {
            return back()->withErrors(['id_robot' => 'Este robot ya tiene una inscripción activa.']);
        }

        $tarifa = $this->tarifas->vigenteParaHoy();
        if ($tarifa === null) {
            return back()->withErrors(['id_robot' => 'No hay una tarifa vigente para hoy.']);
        }

        Inscripcion::create([
            'id_robot' => $robot->id_robot,
            'id_tarifa' => $tarifa->id_tarifa,
            'monto_pagado' => 0,
            'estado_pago' => 'Pendiente',
        ]);

        return back()->with('success', 'Robot inscrito.');
    }

    public function pagar(Inscripcion $inscripcion): RedirectResponse
    {
        $this->authorize('pagar', $inscripcion);

        if ($inscripcion->estado_pago !== 'Pendiente') {
            return back()->withErrors(['estado_pago' => 'Solo se pueden cobrar inscripciones pendientes.']);
        }

        $inscripcion->update([
            'estado_pago' => 'Pagado',
            'monto_pagado' => $inscripcion->tarifa?->monto ?? 0,
        ]);

        return back()->with('success', 'Inscripción marcada como pagada.');
    }

    public function cancelar(Inscripcion $inscripcion): RedirectResponse
    {
        $this->authorize('cancelar', $inscripcion);

        if ($inscripcion->estado_pago !== 'Pendiente') {
            return back()->withErrors(['estado_pago' => 'Solo se pueden cancelar inscripciones pendientes.']);
        }

        $inscripcion->update(['estado_pago' => 'Cancelado']);

        return back()->with('success', 'Inscripción cancelada.');
    }
}
```

- [ ] **Step 7: Rutas**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\InscripcionController;`.
- Dentro de un grupo `['auth','verified']` (puede ser el mismo grupo donde están robots), añadir:
```php
    Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
    Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
    Route::patch('inscripciones/{inscripcion}/pagar', [InscripcionController::class, 'pagar'])->name('inscripciones.pagar');
    Route::patch('inscripciones/{inscripcion}/cancelar', [InscripcionController::class, 'cancelar'])->name('inscripciones.cancelar');
```

- [ ] **Step 8: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=InscripcionTest`
Expected: PASS (8 tests).

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/InscripcionPolicy.php app/Http/Requests/StoreInscripcionRequest.php app/Http/Controllers/InscripcionController.php routes/web.php resources/js/pages/inscripciones/index.tsx tests/Feature/InscripcionTest.php
git commit -m "feat(inscripciones): backend de caja con tarifa vigente y acciones pagar/cancelar"
```

---

## Task 3: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/InscripcionController.ts` (con `index/store/pagar/cancelar`) y `resources/js/routes/inscripciones/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type InscripcionRow = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    piloto: string | null;
    tarifa: string | null;
    monto_pagado: string;
    estado_pago: 'Pendiente' | 'Pagado' | 'Cancelado';
};

export type RobotInscribible = {
    id_robot: number;
    nombre: string;
};

export type TarifaVigente = {
    descripcion: string;
    monto: string;
};
```

- [ ] **Step 3: Ítem de navegación "Inscripciones"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `Receipt` a los iconos importados de `lucide-react`.
- Añadir `import inscripciones from '@/routes/inscripciones';`.
- Añadir al array `mainNavItems` (después de "Robots"):
```tsx
    {
        title: 'Inscripciones',
        href: inscripciones.index(),
        icon: Receipt,
        roles: ['Administrador', 'Piloto'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(inscripciones): tipos frontend y navegacion"
```

---

## Task 4: UI de Inscripciones (modal de inscripción + índice con acciones de caja)

**Files:**
- Create: `resources/js/components/inscripciones/inscribir-robot-dialog.tsx`
- Modify: `resources/js/pages/inscripciones/index.tsx` (reemplaza placeholder)

- [ ] **Step 1: Modal de inscripción**

`resources/js/components/inscripciones/inscribir-robot-dialog.tsx`:
```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InscripcionController from '@/actions/App/Http/Controllers/InscripcionController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RobotInscribible, TarifaVigente } from '@/types';

type Props = {
    robots: RobotInscribible[];
    tarifaVigente: TarifaVigente | null;
    trigger: React.ReactNode;
};

export default function InscribirRobotDialog({ robots, tarifaVigente, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ id_robot: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(InscripcionController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
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
                    <DialogTitle>Inscribir robot</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="id_robot">Robot</Label>
                        <Select value={form.data.id_robot} onValueChange={(v) => form.setData('id_robot', v)}>
                            <SelectTrigger id="id_robot">
                                <SelectValue placeholder="Selecciona un robot" />
                            </SelectTrigger>
                            <SelectContent>
                                {robots.map((r) => (
                                    <SelectItem key={r.id_robot} value={String(r.id_robot)}>
                                        {r.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_robot} />
                    </div>

                    <div className="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                        {tarifaVigente ? (
                            <p>
                                Tarifa vigente: <span className="font-medium">{tarifaVigente.descripcion}</span> — $
                                {tarifaVigente.monto}
                            </p>
                        ) : (
                            <p className="text-red-600 dark:text-red-400">No hay una tarifa vigente para hoy.</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || robots.length === 0 || tarifaVigente === null}>
                            Inscribir
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Página índice**

Reemplazar `resources/js/pages/inscripciones/index.tsx` por:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import InscripcionController from '@/actions/App/Http/Controllers/InscripcionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import InscribirRobotDialog from '@/components/inscripciones/inscribir-robot-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import inscripciones from '@/routes/inscripciones';
import type { Auth, InscripcionRow, RobotInscribible, TarifaVigente } from '@/types';

type PageProps = {
    auth: Auth;
    inscripciones: InscripcionRow[];
    robotsInscribibles: RobotInscribible[];
    tarifaVigente: TarifaVigente | null;
};

const ESTADO_CLASS: Record<InscripcionRow['estado_pago'], string> = {
    Pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Pagado: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Cancelado: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
};

export default function InscripcionesIndex() {
    const { auth, inscripciones: rows, robotsInscribibles, tarifaVigente } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const onError = (errors: Record<string, string>) => {
        const message = Object.values(errors)[0];
        if (message) {
            toast.error(message);
        }
    };

    const pagar = (row: InscripcionRow) => {
        router.patch(InscripcionController.pagar.url(row.id_inscripcion), { preserveScroll: true, onError });
    };

    const cancelar = (row: InscripcionRow) => {
        router.patch(InscripcionController.cancelar.url(row.id_inscripcion), { preserveScroll: true, onError });
    };

    return (
        <>
            <Head title="Inscripciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Inscripciones</h1>
                    <InscribirRobotDialog
                        robots={robotsInscribibles}
                        tarifaVigente={tarifaVigente}
                        trigger={<Button>Inscribir robot</Button>}
                    />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">Categoría</th>
                                {isAdmin && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3">Tarifa</th>
                                <th scope="col" className="p-3">Monto</th>
                                <th scope="col" className="p-3">Estado</th>
                                {isAdmin && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={isAdmin ? 7 : 5}>
                                        No hay inscripciones.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr key={row.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{row.robot ?? '—'}</td>
                                        <td className="p-3">{row.categoria ?? '—'}</td>
                                        {isAdmin && <td className="p-3">{row.piloto ?? '—'}</td>}
                                        <td className="p-3">{row.tarifa ?? '—'}</td>
                                        <td className="p-3">${row.monto_pagado}</td>
                                        <td className="p-3">
                                            <Badge variant="secondary" className={ESTADO_CLASS[row.estado_pago]}>
                                                {row.estado_pago}
                                            </Badge>
                                        </td>
                                        {isAdmin && (
                                            <td className="p-3">
                                                <div className="flex justify-end gap-2">
                                                    {row.estado_pago === 'Pendiente' && (
                                                        <>
                                                            <Button size="sm" onClick={() => pagar(row)}>
                                                                Marcar pagado
                                                            </Button>
                                                            <ConfirmDeleteDialog
                                                                trigger={<Button variant="destructive" size="sm">Cancelar</Button>}
                                                                title="Cancelar inscripción"
                                                                description={`¿Cancelar la inscripción de "${row.robot}"? El robot podrá inscribirse de nuevo.`}
                                                                onConfirm={() => cancelar(row)}
                                                            />
                                                        </>
                                                    )}
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

InscripcionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inscripciones',
            href: inscripciones.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/inscripciones resources/js/pages/inscripciones
git commit -m "feat(inscripciones): UI de caja con modal de inscripcion y acciones"
```

---

## Task 5: Verificación integral de la Fase 2.2

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (91 previos + TarifaServiceTest 3 + InscripcionTest 8 = 102).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(inscripciones): verificacion integral Fase 2.2" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `TarifaService` vigente por fecha / null → Task 1 ✓
- `InscripcionPolicy` (admin before; viewAny/create piloto; pagar/cancelar solo admin) → Task 2 ✓
- `InscripcionController`: index escopado + robotsInscribibles + tarifaVigente; store con ownership/tarifa/duplicado; pagar (monto=tarifa, solo Pendiente); cancelar → Task 2 ✓
- Bloqueo sin tarifa vigente; bloqueo duplicado; re-inscripción tras cancelar → Task 2 (tests) ✓
- Rutas index/store/pagar/cancelar → Task 2 ✓
- Tipos + Wayfinder + nav "Inscripciones" (Admin, Piloto) → Task 3 ✓
- UI: modal inscripción (con preview de tarifa + disable sin tarifa/robots) + tabla con badge de estado + acciones admin (pagar/cancelar con confirmación) + columna Piloto/Acciones solo admin → Task 4 ✓
- Pruebas: unit tarifa, feature auth/scope/store/pagar/cancelar → Tasks 1,2 ✓
- DoD: suite 100%, pint, build → Task 5 ✓

**Riesgo conocido (Wayfinder):** `@/routes/inscripciones` y `@/actions/.../InscripcionController` existen tras `php artisan wayfinder:generate` (Task 3 Step 1). `pagar.url(id)`/`cancelar.url(id)` con escalar deberían tipar (como en 2.1a/2.1b); si no, usar `{ inscripcion: id }`.
