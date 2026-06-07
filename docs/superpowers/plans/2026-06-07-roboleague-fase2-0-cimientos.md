# RoboLeague Fase 2.0 — Cimientos (Roles, Autorización, Dashboard) · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establecer las primitivas de autorización por rol (enum + helpers + middleware) y un dashboard cuyo contenido cambia según el rol del usuario.

**Architecture:** `rol` (ya en `users`) se castea a un enum PHP `RolUsuario`. El modelo `User` gana helpers de rol. Un middleware nativo `role:` protege rutas. Un `DashboardController` arma props distintas por rol y la página React `dashboard.tsx` renderiza tarjetas según esas props. Sin dependencias nuevas; sin Gates (se difieren a los módulos).

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Modelos usan atributos `#[Fillable([...])]`/`#[Hidden([...])]` (ver `app/Models/User.php`).
- `auth.user` se comparte como el modelo completo en `HandleInertiaRequests`; `rol` NO está en `#[Hidden]`, así que ya viaja al frontend (no hay que tocar el middleware Inertia).
- Tipos del frontend: `resources/js/types/auth.ts` define `type User`.
- Páginas leen props con `usePage<PageProps>().props` (ver `resources/js/pages/settings/profile.tsx`).
- El layout se aplica automáticamente por nombre en `resources/js/app.tsx` (default → `AppLayout`). La página dashboard conserva su bloque `Dashboard.layout`.
- Tras tocar PHP: `vendor/bin/pint --dirty --format agent`. Tests: `php artisan test --compact --filter=...`.
- BD PostgreSQL; tests usan `roboleague_testing` con RefreshDatabase.

---

## File Structure

- Create: `app/Enums/RolUsuario.php` — enum string-backed de los 4 roles.
- Modify: `app/Models/User.php` — cast `rol`→enum + helpers de rol.
- Create: `app/Http/Middleware/EnsureUserHasRole.php` — guard por rol.
- Modify: `bootstrap/app.php` — alias de middleware `role`.
- Create: `app/Http/Controllers/DashboardController.php` — props por rol.
- Modify: `routes/web.php` — ruta `dashboard` apunta al controlador.
- Create: `resources/js/components/stat-card.tsx` — tarjeta reutilizable.
- Modify: `resources/js/pages/dashboard.tsx` — render por rol.
- Modify: `resources/js/types/auth.ts` — añadir `rol` (y `apellidos`/`telefono`) al type `User`.
- Tests: `tests/Unit/RolUsuarioTest.php`, `tests/Feature/RoleMiddlewareTest.php`, `tests/Feature/DashboardTest.php`.

---

## Task 0: Rama de trabajo

**Files:** ninguno.

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git pull --ff-only 2>/dev/null; git checkout -b feature/roboleague-fase2-0
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-0'`.

---

## Task 1: Enum `RolUsuario` + helpers en `User`

**Files:**
- Create: `app/Enums/RolUsuario.php`
- Modify: `app/Models/User.php`
- Test: `tests/Unit/RolUsuarioTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Unit/RolUsuarioTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_enum_tiene_los_cuatro_roles(): void
    {
        $this->assertSame('Administrador', RolUsuario::Administrador->value);
        $this->assertSame('Juez', RolUsuario::Juez->value);
        $this->assertSame('Coach', RolUsuario::Coach->value);
        $this->assertSame('Piloto', RolUsuario::Piloto->value);
    }

    public function test_rol_se_castea_a_enum(): void
    {
        $user = User::factory()->juez()->create();

        $this->assertInstanceOf(RolUsuario::class, $user->rol);
        $this->assertSame(RolUsuario::Juez, $user->rol);
    }

    public function test_helpers_de_rol(): void
    {
        $juez = User::factory()->juez()->create();

        $this->assertTrue($juez->isJuez());
        $this->assertFalse($juez->isAdministrador());
        $this->assertTrue($juez->hasRole(RolUsuario::Juez, RolUsuario::Administrador));
        $this->assertFalse($juez->hasRole(RolUsuario::Coach));
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=RolUsuarioTest`
Expected: FAIL (`Class "App\Enums\RolUsuario" not found`).

- [ ] **Step 3: Crear el enum**

`app/Enums/RolUsuario.php`:
```php
<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'Administrador';
    case Juez = 'Juez';
    case Coach = 'Coach';
    case Piloto = 'Piloto';
}
```

- [ ] **Step 4: Castear `rol` y añadir helpers en `User`**

En `app/Models/User.php`:
- Añadir imports al inicio: `use App\Enums\RolUsuario;`.
- En el método `casts()`, añadir la clave `'rol' => RolUsuario::class,` al array retornado.
- Añadir estos métodos dentro de la clase (después de `casts()`):
```php
public function hasRole(RolUsuario ...$roles): bool
{
    return in_array($this->rol, $roles, true);
}

public function isAdministrador(): bool
{
    return $this->hasRole(RolUsuario::Administrador);
}

public function isJuez(): bool
{
    return $this->hasRole(RolUsuario::Juez);
}

public function isCoach(): bool
{
    return $this->hasRole(RolUsuario::Coach);
}

public function isPiloto(): bool
{
    return $this->hasRole(RolUsuario::Piloto);
}
```

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=RolUsuarioTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/RolUsuario.php app/Models/User.php tests/Unit/RolUsuarioTest.php
git commit -m "feat(auth): enum RolUsuario y helpers de rol en User"
```

---

## Task 2: Middleware `EnsureUserHasRole` (alias `role`)

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/RoleMiddlewareTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/RoleMiddlewareTest.php` (define una ruta temporal protegida y verifica 403/200):
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:Administrador'])
            ->get('/_test/solo-admin', fn () => 'ok');
    }

    public function test_administrador_puede_acceder(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->get('/_test/solo-admin')->assertOk();
    }

    public function test_juez_recibe_403(): void
    {
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)->get('/_test/solo-admin')->assertForbidden();
    }

    public function test_invitado_es_bloqueado(): void
    {
        $this->get('/_test/solo-admin')->assertRedirect();
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=RoleMiddlewareTest`
Expected: FAIL (alias `role` no definido → `Target class [role] does not exist` o similar).

- [ ] **Step 3: Crear el middleware**

`app/Http/Middleware/EnsureUserHasRole.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->rol->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar el alias `role`**

En `bootstrap/app.php`, dentro del callback `->withMiddleware(function (Middleware $middleware): void { ... })`, añadir (después del bloque `$middleware->web(append: [...]);`):
```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
]);
```

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=RoleMiddlewareTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureUserHasRole.php bootstrap/app.php tests/Feature/RoleMiddlewareTest.php
git commit -m "feat(auth): middleware role para proteger rutas por rol"
```

---

## Task 3: `DashboardController` con props por rol

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/DashboardTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_ve_metricas_globales(): void
    {
        InspeccionChecklist::factory()->create(['estado_aprobacion' => 'Pendiente']);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('stats')
            );
    }

    public function test_coach_solo_ve_sus_robots(): void
    {
        $coach = User::factory()->coach()->create();
        $otro = User::factory()->coach()->create();
        $miRobot = Robot::factory()->create(['id_piloto' => $coach->id, 'nombre' => 'MiBot']);
        Robot::factory()->create(['id_piloto' => $otro->id, 'nombre' => 'AjenoBot']);

        $this->actingAs($coach)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('robots', 1)
                ->where('robots.0.nombre', 'MiBot')
            );
    }

    public function test_juez_ve_inspecciones_pendientes(): void
    {
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('stats')
            );
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: FAIL (la ruta dashboard aún es `Route::inertia` sin props `stats`/`robots`).

- [ ] **Step 3: Crear el controlador**

`app/Http/Controllers/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Models\Encuentro;
use App\Models\InspeccionChecklist;
use App\Models\Inscripcion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $props = match ($user->rol) {
            RolUsuario::Administrador => $this->adminStats(),
            RolUsuario::Juez => $this->juezStats(),
            RolUsuario::Coach, RolUsuario::Piloto => $this->robotOwnerStats($user),
        };

        return Inertia::render('dashboard', $props);
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int|string}>}
     */
    private function adminStats(): array
    {
        return [
            'stats' => [
                ['label' => 'Robots inscritos', 'value' => Inscripcion::distinct()->count('id_robot')],
                ['label' => 'Inscripciones pagadas', 'value' => Inscripcion::where('estado_pago', 'Pagado')->count()],
                ['label' => 'Inscripciones pendientes', 'value' => Inscripcion::where('estado_pago', 'Pendiente')->count()],
                ['label' => 'Total recaudado', 'value' => '$'.number_format((float) Inscripcion::where('estado_pago', 'Pagado')->sum('monto_pagado'), 2)],
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count()],
            ],
        ];
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int|string}>}
     */
    private function juezStats(): array
    {
        return [
            'stats' => [
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count()],
                ['label' => 'Encuentros por resolver', 'value' => Encuentro::whereDoesntHave('participantes', fn ($q) => $q->where('es_ganador', true))->count()],
            ],
        ];
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int|string}>, robots: array<int, array{id_robot: int, nombre: string, categoria: ?string, estado_pago: string}>}
     */
    private function robotOwnerStats(User $user): array
    {
        $robots = Robot::where('id_piloto', $user->id)
            ->with(['categoria', 'inscripciones'])
            ->get()
            ->map(fn (Robot $robot) => [
                'id_robot' => $robot->id_robot,
                'nombre' => $robot->nombre,
                'categoria' => $robot->categoria?->nombre,
                'estado_pago' => $robot->inscripciones->last()?->estado_pago ?? 'Sin inscripción',
            ])
            ->values()
            ->all();

        return [
            'stats' => [
                ['label' => 'Mis robots', 'value' => count($robots)],
            ],
            'robots' => $robots,
        ];
    }
}
```

- [ ] **Step 4: Apuntar la ruta al controlador**

En `routes/web.php`:
- Añadir import al inicio: `use App\Http\Controllers\DashboardController;`.
- Reemplazar:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});
```
por:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
```

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/DashboardTest.php
git commit -m "feat(dashboard): props por rol via DashboardController"
```

---

## Task 4: Frontend — tipo `rol`, `StatCard` y dashboard por rol

**Files:**
- Modify: `resources/js/types/auth.ts`
- Create: `resources/js/components/stat-card.tsx`
- Modify: `resources/js/pages/dashboard.tsx`

- [ ] **Step 1: Añadir `rol` (y campos de RoboLeague) al type `User`**

En `resources/js/types/auth.ts`, dentro de `export type User = { ... }`, añadir tras `email`:
```ts
    apellidos: string;
    telefono: string | null;
    rol: 'Administrador' | 'Juez' | 'Coach' | 'Piloto';
```

- [ ] **Step 2: Crear el componente `StatCard`**

`resources/js/components/stat-card.tsx`:
```tsx
type StatCardProps = {
    label: string;
    value: string | number;
};

export default function StatCard({ label, value }: StatCardProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-2 text-3xl font-semibold">{value}</p>
        </div>
    );
}
```

- [ ] **Step 3: Reescribir `dashboard.tsx` para renderizar por rol**

Reemplazar el contenido de `resources/js/pages/dashboard.tsx` por:
```tsx
import { Head, usePage } from '@inertiajs/react';
import StatCard from '@/components/stat-card';
import { dashboard } from '@/routes';
import type { Auth } from '@/types';

type Stat = { label: string; value: string | number };
type RobotRow = {
    id_robot: number;
    nombre: string;
    categoria: string | null;
    estado_pago: string;
};

type DashboardProps = {
    auth: Auth;
    stats: Stat[];
    robots?: RobotRow[];
};

export default function Dashboard() {
    const { auth, stats, robots } = usePage<DashboardProps>().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Hola, {auth.user.name}</h1>
                    <p className="text-sm text-muted-foreground">Rol: {auth.user.rol}</p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {stats.map((stat) => (
                        <StatCard key={stat.label} label={stat.label} value={stat.value} />
                    ))}
                </div>

                {robots && (
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <tr>
                                    <th className="p-3">Robot</th>
                                    <th className="p-3">Categoría</th>
                                    <th className="p-3">Estado de pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                {robots.length === 0 ? (
                                    <tr>
                                        <td className="p-3 text-muted-foreground" colSpan={3}>
                                            Aún no tienes robots registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    robots.map((robot) => (
                                        <tr key={robot.id_robot} className="border-b border-sidebar-border/40 last:border-0">
                                            <td className="p-3">{robot.nombre}</td>
                                            <td className="p-3">{robot.categoria ?? '—'}</td>
                                            <td className="p-3">{robot.estado_pago}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
```

- [ ] **Step 4: Verificar el build del frontend**

Run: `npm run build`
Expected: build exitoso sin errores de TypeScript.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/auth.ts resources/js/components/stat-card.tsx resources/js/pages/dashboard.tsx
git commit -m "feat(dashboard): UI por rol con StatCard y tabla de robots"
```

---

## Task 5: Verificación integral de la Fase 2.0

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa de pruebas**

Run: `php artisan test --compact`
Expected: todas PASS (los 60 previos + RolUsuarioTest 3 + RoleMiddlewareTest 3 + DashboardTest 3).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(auth): verificacion integral Fase 2.0" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Enum `RolUsuario` (4 casos) → Task 1 ✓
- `User` cast + helpers (`hasRole`, `isAdministrador`/`isJuez`/`isCoach`/`isPiloto`) → Task 1 ✓
- Middleware `EnsureUserHasRole` alias `role` + 403/permitir → Task 2 ✓
- `rol` disponible y tipado en el frontend → Task 4 (auth.ts) ✓ (en backend ya viaja vía auth.user, no oculto)
- Dashboard por rol (Admin métricas/caja, Juez inspecciones/encuentros, Coach/Piloto sus robots) → Tasks 3 (controlador) + 4 (UI) ✓
- Componente `StatCard` reutilizable → Task 4 ✓
- Pruebas unit (enum/helpers), feature (middleware 403/200, dashboard props con `assertInertia`) → Tasks 1,2,3 ✓
- DoD: suite 100%, pint, build → Task 5 ✓

**Nota:** los Gates por capacidad y la navegación lateral con secciones futuras están explícitamente fuera de alcance (2.1+), por diseño.
