# RoboLeague Fase 2.1b — CRUD de Robots · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CRUD de Robots con autorización por propiedad (Administrador gestiona todos; Piloto solo los suyos; Juez/Coach sin acceso), UI lista + modales con selects de catálogo.

**Architecture:** `RobotPolicy` (auto-descubierta) gobierna el acceso por registro; `RobotController` usa `authorizeResource` y escopa el index por rol; al crear/editar como Piloto, `id_piloto` se fuerza al usuario autenticado. Frontend Inertia/React con tabla + modal `useForm` y selects poblados desde el backend, vía Wayfinder. Reusa `ConfirmDeleteDialog` y el patrón de 2.1a.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Modelos `#[Fillable([...])]`; controladores extienden `App\Http\Controllers\Controller`.
- CRUD pattern de 2.1a: index page + `*-form-dialog.tsx` (modal `useForm`) + `ConfirmDeleteDialog` (`@/components/confirm-delete-dialog`). Errores de borrado → `toast` de `sonner` en `onError`.
- Frontend llama backend SOLO vía Wayfinder (default import `import RobotController from '@/actions/App/Http/Controllers/RobotController'`, `RobotController.store.url()`, `.update.url(id)`, `.destroy.url(id)`; rutas `import robots from '@/routes/robots'`, `robots.index()`). `resources/js/actions` y `resources/js/routes` están gitignored (Wayfinder los regenera); correr `php artisan wayfinder:generate` tras añadir rutas.
- Modelo `Robot` (Fase 1): tabla `robots`, PK `id_robot`, `#[Fillable(['nombre','id_piloto','id_institucion','id_categoria'])]`, relaciones `piloto()` (belongsTo User 'id_piloto','id'), `institucion()`, `categoria()`. `RobotFactory` crea con sub-factories.
- `User` tiene `RolUsuario` cast + helpers `isAdministrador()/isPiloto()`; factory estados `juez()/coach()`, y `->create(['rol'=>'Piloto'])`/`['rol'=>'Administrador']`.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests: `php artisan test --compact --filter=...`. Frontend gate: `npm run build`. BD PostgreSQL; RefreshDatabase contra `roboleague_testing`.

---

## File Structure

**Backend:**
- Create: `app/Policies/RobotPolicy.php` — autorización por propiedad.
- Create: `app/Http/Requests/RobotRequest.php` — validación (reglas condicionales admin/piloto).
- Create: `app/Http/Controllers/RobotController.php` — index escopado + store/update con forzado de id_piloto + destroy.
- Modify: `routes/web.php` — resource `robots` en grupo `auth, verified`.

**Frontend:**
- Modify: `resources/js/types/models.ts` — `RobotRow`, `CategoriaOpcion`, `InstitucionOpcion`, `PilotoOpcion`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítem "Robots" (`roles: ['Administrador','Piloto']`).
- Create: `resources/js/components/robots/robot-form-dialog.tsx`.
- Create: `resources/js/pages/robots/index.tsx`.

**Tests:** `tests/Feature/RobotCrudTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-1b
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-1b'`.

---

## Task 1: Backend de Robots (policy, request, controlador, ruta, test)

**Files:**
- Create: `app/Policies/RobotPolicy.php`
- Create: `app/Http/Requests/RobotRequest.php`
- Create: `app/Http/Controllers/RobotController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/robots/index.tsx` (placeholder mínimo, para que el assert de componente Inertia resuelva en los tests; se reemplaza en Task 3)
- Test: `tests/Feature/RobotCrudTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/RobotCrudTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RobotCrudTest extends TestCase
{
    use RefreshDatabase;

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
        $this->actingAs(User::factory()->juez()->create())->get('/robots')->assertForbidden();
        $this->actingAs(User::factory()->coach()->create())->get('/robots')->assertForbidden();
    }

    public function test_admin_ve_todos_los_robots(): void
    {
        Robot::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->get('/robots')
            ->assertInertia(fn (Assert $page) => $page->component('robots/index')->has('robots', 2));
    }

    public function test_piloto_solo_ve_sus_robots(): void
    {
        $piloto = $this->piloto();
        Robot::factory()->create(['id_piloto' => $piloto->id, 'nombre' => 'MiBot']);
        Robot::factory()->create(['nombre' => 'AjenoBot']);

        $this->actingAs($piloto)
            ->get('/robots')
            ->assertInertia(fn (Assert $page) => $page
                ->component('robots/index')
                ->has('robots', 1)
                ->where('robots.0.nombre', 'MiBot')
            );
    }

    public function test_admin_crea_robot_para_un_piloto(): void
    {
        $piloto = $this->piloto();
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin())
            ->post('/robots', [
                'nombre' => 'Rayo', 'id_categoria' => $categoria->id_categoria,
                'id_institucion' => null, 'id_piloto' => $piloto->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['nombre' => 'Rayo', 'id_piloto' => $piloto->id]);
    }

    public function test_piloto_se_auto_asigna_aunque_envie_otro_id(): void
    {
        $piloto = $this->piloto();
        $otro = $this->piloto();
        $categoria = Categoria::factory()->create();

        $this->actingAs($piloto)
            ->post('/robots', [
                'nombre' => 'Propio', 'id_categoria' => $categoria->id_categoria,
                'id_institucion' => null, 'id_piloto' => $otro->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['nombre' => 'Propio', 'id_piloto' => $piloto->id]);
        $this->assertDatabaseMissing('robots', ['nombre' => 'Propio', 'id_piloto' => $otro->id]);
    }

    public function test_piloto_no_puede_editar_robot_ajeno(): void
    {
        $ajeno = Robot::factory()->create();
        $categoria = $ajeno->id_categoria;

        $this->actingAs($this->piloto())
            ->put("/robots/{$ajeno->id_robot}", [
                'nombre' => 'Hackeado', 'id_categoria' => $categoria, 'id_institucion' => null,
            ])
            ->assertForbidden();
    }

    public function test_piloto_edita_su_robot(): void
    {
        $piloto = $this->piloto();
        $robot = Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($piloto)
            ->put("/robots/{$robot->id_robot}", [
                'nombre' => 'Mejorado', 'id_categoria' => $robot->id_categoria, 'id_institucion' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'nombre' => 'Mejorado']);
    }

    public function test_admin_borra_cualquier_robot(): void
    {
        $robot = Robot::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/robots/{$robot->id_robot}")
            ->assertRedirect();

        $this->assertDatabaseMissing('robots', ['id_robot' => $robot->id_robot]);
    }

    public function test_id_categoria_es_requerido(): void
    {
        $this->actingAs($this->admin())
            ->post('/robots', ['nombre' => 'SinCat', 'id_piloto' => $this->piloto()->id])
            ->assertSessionHasErrors('id_categoria');
    }

    public function test_id_piloto_debe_ser_rol_piloto(): void
    {
        $juez = User::factory()->juez()->create();
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin())
            ->post('/robots', [
                'nombre' => 'X', 'id_categoria' => $categoria->id_categoria, 'id_piloto' => $juez->id,
            ])
            ->assertSessionHasErrors('id_piloto');
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=RobotCrudTest`
Expected: FAIL (ruta/controlador/policy inexistentes).

- [ ] **Step 3: Placeholder de la página índice**

`resources/js/pages/robots/index.tsx`:
```tsx
export default function RobotsIndex() {
    return <div>Robots</div>;
}
```

- [ ] **Step 4: Policy**

`app/Policies/RobotPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Robot;
use App\Models\User;

class RobotPolicy
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

    public function update(User $user, Robot $robot): bool
    {
        return $user->isPiloto() && (int) $robot->id_piloto === $user->id;
    }

    public function delete(User $user, Robot $robot): bool
    {
        return $user->isPiloto() && (int) $robot->id_piloto === $user->id;
    }
}
```

- [ ] **Step 5: Form Request**

`app/Http/Requests/RobotRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RobotRequest extends FormRequest
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
        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
            'id_institucion' => ['nullable', 'integer', 'exists:instituciones,id_institucion'],
        ];

        if ($this->user()->isAdministrador()) {
            $rules['id_piloto'] = ['required', 'integer', Rule::exists('users', 'id')->where('rol', 'Piloto')];
        } else {
            $rules['id_piloto'] = ['nullable'];
        }

        return $rules;
    }
}
```

- [ ] **Step 6: Controlador**

`app/Http/Controllers/RobotController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RobotRequest;
use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RobotController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Robot::class, 'robot');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Robot::with(['piloto', 'institucion', 'categoria'])->orderBy('nombre');

        if (! $user->isAdministrador()) {
            $query->where('id_piloto', $user->id);
        }

        $robots = $query->get()->map(fn (Robot $robot) => [
            'id_robot' => $robot->id_robot,
            'nombre' => $robot->nombre,
            'categoria' => $robot->categoria?->nombre,
            'institucion' => $robot->institucion?->nombre,
            'piloto' => $robot->piloto ? $robot->piloto->name.' '.$robot->piloto->apellidos : null,
            'id_piloto' => $robot->id_piloto,
        ])->values();

        return Inertia::render('robots/index', [
            'robots' => $robots,
            'categorias' => Categoria::orderBy('nombre')->get(['id_categoria', 'nombre']),
            'instituciones' => Institucion::orderBy('nombre')->get(['id_institucion', 'nombre']),
            'pilotos' => $user->isAdministrador()
                ? User::where('rol', 'Piloto')->orderBy('name')->get(['id', 'name', 'apellidos'])
                    ->map(fn (User $p) => ['id' => $p->id, 'nombre' => $p->name.' '.$p->apellidos])->values()
                : [],
        ]);
    }

    public function store(RobotRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->user()->isAdministrador()) {
            $data['id_piloto'] = $request->user()->id;
        }

        Robot::create($data);

        return back()->with('success', 'Robot creado.');
    }

    public function update(RobotRequest $request, Robot $robot): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->user()->isAdministrador()) {
            $data['id_piloto'] = $request->user()->id;
        }

        $robot->update($data);

        return back()->with('success', 'Robot actualizado.');
    }

    public function destroy(Robot $robot): RedirectResponse
    {
        $robot->delete();

        return back()->with('success', 'Robot eliminado.');
    }
}
```

- [ ] **Step 7: Ruta**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\RobotController;`.
- Añadir un grupo (separado del grupo `role:Administrador`, porque robots lo usan también Pilotos; la policy autoriza):
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('robots', RobotController::class)->only(['index', 'store', 'update', 'destroy']);
});
```

- [ ] **Step 8: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=RobotCrudTest`
Expected: PASS (10 tests).

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/RobotPolicy.php app/Http/Requests/RobotRequest.php app/Http/Controllers/RobotController.php routes/web.php resources/js/pages/robots/index.tsx tests/Feature/RobotCrudTest.php
git commit -m "feat(robots): CRUD backend con policy de propiedad y forzado de piloto"
```

---

## Task 2: Wayfinder + tipos + navegación

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera `resources/js/actions/App/Http/Controllers/RobotController.ts` y `resources/js/routes/robots/index.ts`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`, añadir al final:
```ts
export type RobotRow = {
    id_robot: number;
    nombre: string;
    categoria: string | null;
    institucion: string | null;
    piloto: string | null;
    id_piloto: number;
};

export type CategoriaOpcion = {
    id_categoria: number;
    nombre: string;
};

export type InstitucionOpcion = {
    id_institucion: number;
    nombre: string;
};

export type PilotoOpcion = {
    id: number;
    nombre: string;
};
```

- [ ] **Step 3: Ítem de navegación "Robots"**

En `resources/js/components/app-sidebar.tsx`:
- Añadir `Bot` a los iconos importados de `lucide-react` (junto a `Building2`, `Users`, etc.).
- Añadir import: `import robots from '@/routes/robots';`.
- Añadir al array `mainNavItems` (después de "Usuarios"):
```tsx
    {
        title: 'Robots',
        href: robots.index(),
        icon: Bot,
        roles: ['Administrador', 'Piloto'],
    },
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(robots): tipos frontend y navegacion"
```

---

## Task 3: UI de Robots (modal de formulario + página índice)

**Files:**
- Create: `resources/js/components/robots/robot-form-dialog.tsx`
- Modify: `resources/js/pages/robots/index.tsx` (reemplaza el placeholder)

- [ ] **Step 1: Modal de formulario**

`resources/js/components/robots/robot-form-dialog.tsx`:
```tsx
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RobotController from '@/actions/App/Http/Controllers/RobotController';
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
import type { Auth, CategoriaOpcion, InstitucionOpcion, PilotoOpcion, RobotRow } from '@/types';

const SIN_INSTITUCION = 'none';

type Props = {
    robot?: RobotRow;
    categorias: CategoriaOpcion[];
    instituciones: InstitucionOpcion[];
    pilotos: PilotoOpcion[];
    trigger: React.ReactNode;
};

export default function RobotFormDialog({ robot, categorias, instituciones, pilotos, trigger }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.user.rol === 'Administrador';
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(robot);

    const form = useForm({
        nombre: robot?.nombre ?? '',
        id_categoria: '',
        id_institucion: SIN_INSTITUCION,
        id_piloto: robot ? String(robot.id_piloto) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form
            .transform((data) => ({
                ...data,
                id_institucion: data.id_institucion === SIN_INSTITUCION ? null : data.id_institucion,
                id_piloto: isAdmin ? data.id_piloto : undefined,
            }))
            .submit(
                isEdit && robot ? 'put' : 'post',
                isEdit && robot ? RobotController.update.url(robot.id_robot) : RobotController.store.url(),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setOpen(false);
                        if (!isEdit) {
                            form.reset();
                        }
                    },
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
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar robot' : 'Nuevo robot'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />
                        <InputError message={form.errors.nombre} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_categoria">Categoría</Label>
                        <Select value={form.data.id_categoria} onValueChange={(v) => form.setData('id_categoria', v)}>
                            <SelectTrigger id="id_categoria">
                                <SelectValue placeholder="Selecciona una categoría" />
                            </SelectTrigger>
                            <SelectContent>
                                {categorias.map((c) => (
                                    <SelectItem key={c.id_categoria} value={String(c.id_categoria)}>
                                        {c.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_categoria} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_institucion">Institución</Label>
                        <Select value={form.data.id_institucion} onValueChange={(v) => form.setData('id_institucion', v)}>
                            <SelectTrigger id="id_institucion">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SIN_INSTITUCION}>Sin institución</SelectItem>
                                {instituciones.map((i) => (
                                    <SelectItem key={i.id_institucion} value={String(i.id_institucion)}>
                                        {i.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_institucion} />
                    </div>

                    {isAdmin && (
                        <div className="grid gap-2">
                            <Label htmlFor="id_piloto">Piloto</Label>
                            <Select value={form.data.id_piloto} onValueChange={(v) => form.setData('id_piloto', v)}>
                                <SelectTrigger id="id_piloto">
                                    <SelectValue placeholder="Selecciona un piloto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {pilotos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.id_piloto} />
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Guardar' : 'Crear'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```
(Nota: en edición, `id_categoria` arranca vacío y el admin/piloto re-selecciona la categoría; esto es aceptable para esta fase. El `id_robot` se pasa como escalar a `update.url(...)`; si el build se queja del tipo, usar `{ robot: robot.id_robot }`.)

- [ ] **Step 2: Página índice**

Reemplazar `resources/js/pages/robots/index.tsx` por:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import RobotController from '@/actions/App/Http/Controllers/RobotController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import RobotFormDialog from '@/components/robots/robot-form-dialog';
import { Button } from '@/components/ui/button';
import robots from '@/routes/robots';
import type { Auth, CategoriaOpcion, InstitucionOpcion, PilotoOpcion, RobotRow } from '@/types';

type PageProps = {
    auth: Auth;
    robots: RobotRow[];
    categorias: CategoriaOpcion[];
    instituciones: InstitucionOpcion[];
    pilotos: PilotoOpcion[];
};

export default function RobotsIndex() {
    const { auth, robots: rows, categorias, instituciones, pilotos } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const destroy = (robot: RobotRow) => {
        router.delete(RobotController.destroy.url(robot.id_robot), {
            preserveScroll: true,
            onError: (errors) => {
                const message = Object.values(errors)[0];
                if (message) {
                    toast.error(message);
                }
            },
        });
    };

    return (
        <>
            <Head title="Robots" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Robots</h1>
                    <RobotFormDialog
                        categorias={categorias}
                        instituciones={instituciones}
                        pilotos={pilotos}
                        trigger={<Button>Nuevo robot</Button>}
                    />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Categoría</th>
                                <th scope="col" className="p-3">Institución</th>
                                {isAdmin && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={isAdmin ? 5 : 4}>
                                        No hay robots registrados.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((robot) => (
                                    <tr key={robot.id_robot} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{robot.nombre}</td>
                                        <td className="p-3">{robot.categoria ?? '—'}</td>
                                        <td className="p-3">{robot.institucion ?? '—'}</td>
                                        {isAdmin && <td className="p-3">{robot.piloto ?? '—'}</td>}
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <RobotFormDialog
                                                    robot={robot}
                                                    categorias={categorias}
                                                    instituciones={instituciones}
                                                    pilotos={pilotos}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar robot"
                                                    description={`¿Seguro que deseas eliminar "${robot.nombre}"?`}
                                                    onConfirm={() => destroy(robot)}
                                                />
                                            </div>
                                        </td>
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

RobotsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Robots',
            href: robots.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/robots resources/js/pages/robots
git commit -m "feat(robots): UI lista + modal con selects de catalogo"
```

---

## Task 4: Verificación integral de la Fase 2.1b

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (81 previos + RobotCrudTest 10 = 91).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(robots): verificacion integral Fase 2.1b" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `RobotPolicy` (before admin; viewAny/create piloto; update/delete owner) → Task 1 ✓
- `RobotController` con `authorizeResource`, index escopado por rol, catálogos + pilotos solo-admin → Task 1 ✓
- Forzado de `id_piloto` al piloto autenticado en store/update → Task 1 ✓
- `RobotRequest` reglas condicionales (id_piloto required+rol Piloto solo admin; id_categoria exists; id_institucion nullable) → Task 1 ✓
- Ruta resource en grupo `auth, verified` (policy autoriza) → Task 1 ✓
- Tipos frontend + Wayfinder + nav "Robots" (Administrador, Piloto) → Task 2 ✓
- UI lista + modal con selects (categoría/institución/piloto-solo-admin) + "Sin institución"→null + ConfirmDeleteDialog + toast en onError → Task 3 ✓
- Columna Piloto oculta para rol Piloto → Task 3 ✓
- Pruebas: 403 Juez/Coach, scope Piloto, auto-asignación, edición ajena 403, validación → Task 1 ✓
- DoD: suite 100%, pint, build → Task 4 ✓

**Riesgo conocido (Wayfinder):** `@/routes/robots` y `@/actions/.../RobotController` existen tras `php artisan wayfinder:generate` (Task 2 Step 1). Si `update.url(robot.id_robot)`/`destroy.url(...)` no tipa con escalar, pasar `{ robot: id }` según la firma generada (igual que en 2.1a, donde el escalar funcionó).
