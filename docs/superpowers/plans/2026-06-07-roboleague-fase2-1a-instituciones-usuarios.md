# RoboLeague Fase 2.1a — CRUD Instituciones y Usuarios · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CRUD de Instituciones y Usuarios accesible solo al Administrador, con UI de lista + modales y navegación por rol.

**Architecture:** Controladores resourceful (`only index/store/update/destroy`) protegidos por `role:Administrador`, Form Requests para validación, modelos Eloquent de Fase 1. Frontend Inertia/React: páginas índice con tabla + modales `Dialog` con `useForm`, llamando a acciones generadas por Wayfinder. Borrado de usuarios con guardas (auto-borrado y referencias). Sin Gates nuevos.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Modelos: atributos `#[Fillable([...])]`. `casts()` es método. Controladores extienden `App\Http\Controllers\Controller`.
- Páginas React leen props con `usePage<T>().props`; formularios con `useForm`; errores con `<InputError message=... />`.
- Acciones backend desde el frontend SIEMPRE vía Wayfinder (default import `import XController from '@/actions/App/Http/Controllers/XController'`, luego `XController.store.url()`, `.update.url(id)`, `.destroy.url(id)`). No hardcodear URLs.
- Wayfinder regenera en `npm run build`/`dev`; tras añadir rutas, ejecutar `php artisan wayfinder:generate` para que existan los imports `@/actions/...`.
- Kit UI en `@/components/ui/*` (Dialog, Input, Label, Select, Button, Badge). `InputError` en `@/components/input-error`.
- Sidebar: array `mainNavItems` en `resources/js/components/app-sidebar.tsx`; tipo `NavItem` en `resources/js/types/navigation.ts`.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests: `php artisan test --compact --filter=...`. Frontend gate: `npm run build`.
- BD PostgreSQL; tests con RefreshDatabase contra `roboleague_testing`. `User` factory tiene estados `juez()`, `coach()`; Administrador con `->create(['rol' => 'Administrador'])`.

---

## File Structure

**Backend:**
- Modify: `app/Models/Institucion.php` — `getRouteKeyName()` → `id_institucion`.
- Create: `app/Http/Controllers/InstitucionController.php`, `app/Http/Controllers/UsuarioController.php`.
- Create: `app/Http/Requests/InstitucionRequest.php` (reglas idénticas store/update), `app/Http/Requests/StoreUsuarioRequest.php`, `app/Http/Requests/UpdateUsuarioRequest.php`.
- Modify: `routes/web.php` — grupo admin con los 2 resources.

**Frontend:**
- Modify: `resources/js/types/navigation.ts` — `NavItem.roles?`.
- Create: `resources/js/types/models.ts` + Modify `resources/js/types/index.ts` — tipos `Institucion`, `UsuarioRow`.
- Modify: `resources/js/components/app-sidebar.tsx` — ítems con `roles` + filtro por `auth.user.rol`.
- Create: `resources/js/components/confirm-delete-dialog.tsx` — diálogo de confirmación reutilizable.
- Create: `resources/js/components/instituciones/institucion-form-dialog.tsx`, `resources/js/pages/instituciones/index.tsx`.
- Create: `resources/js/components/usuarios/usuario-form-dialog.tsx`, `resources/js/pages/usuarios/index.tsx`.

**Tests:** `tests/Feature/InstitucionCrudTest.php`, `tests/Feature/UsuarioCrudTest.php`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-fase2-1a
```
Expected: `Switched to a new branch 'feature/roboleague-fase2-1a'`.

---

## Task 1: Backend de Instituciones (modelo binding, controlador, request, ruta, test)

**Files:**
- Modify: `app/Models/Institucion.php`
- Create: `app/Http/Requests/InstitucionRequest.php`
- Create: `app/Http/Controllers/InstitucionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InstitucionCrudTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/InstitucionCrudTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitucionCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_no_admin_recibe_403(): void
    {
        $this->actingAs(User::factory()->juez()->create())
            ->get('/instituciones')
            ->assertForbidden();
    }

    public function test_admin_ve_el_indice(): void
    {
        Institucion::factory()->create(['nombre' => 'TESCHA']);

        $this->actingAs($this->admin())
            ->get('/instituciones')
            ->assertInertia(fn (Assert $page) => $page
                ->component('instituciones/index')
                ->has('instituciones', 1)
            );
    }

    public function test_admin_crea_institucion(): void
    {
        $this->actingAs($this->admin())
            ->post('/instituciones', ['nombre' => 'Tec', 'tipo' => 'Privada', 'estado' => 'Nuevo León'])
            ->assertRedirect();

        $this->assertDatabaseHas('instituciones', ['nombre' => 'Tec', 'tipo' => 'Privada']);
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post('/instituciones', ['nombre' => 'X', 'tipo' => 'Galactica', 'estado' => 'CDMX'])
            ->assertSessionHasErrors('tipo');
    }

    public function test_admin_actualiza_institucion(): void
    {
        $institucion = Institucion::factory()->create(['nombre' => 'Viejo']);

        $this->actingAs($this->admin())
            ->put("/instituciones/{$institucion->id_institucion}", ['nombre' => 'Nuevo', 'tipo' => 'Pública', 'estado' => 'México'])
            ->assertRedirect();

        $this->assertDatabaseHas('instituciones', ['id_institucion' => $institucion->id_institucion, 'nombre' => 'Nuevo']);
    }

    public function test_borrar_institucion_deja_null_en_robot(): void
    {
        $institucion = Institucion::factory()->create();
        $robot = Robot::factory()->create(['id_institucion' => $institucion->id_institucion]);

        $this->actingAs($this->admin())
            ->delete("/instituciones/{$institucion->id_institucion}")
            ->assertRedirect();

        $this->assertDatabaseMissing('instituciones', ['id_institucion' => $institucion->id_institucion]);
        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'id_institucion' => null]);
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=InstitucionCrudTest`
Expected: FAIL (rutas/controlador inexistentes).

- [ ] **Step 3: Route key binding en el modelo**

En `app/Models/Institucion.php`, añadir dentro de la clase:
```php
public function getRouteKeyName(): string
{
    return 'id_institucion';
}
```

- [ ] **Step 4: Form Request**

`app/Http/Requests/InstitucionRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstitucionRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:Pública,Privada,Independiente'],
            'estado' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 5: Controlador**

`app/Http/Controllers/InstitucionController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitucionRequest;
use App\Models\Institucion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstitucionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('instituciones/index', [
            'instituciones' => Institucion::orderBy('nombre')->get(),
        ]);
    }

    public function store(InstitucionRequest $request): RedirectResponse
    {
        Institucion::create($request->validated());

        return back()->with('success', 'Institución creada.');
    }

    public function update(InstitucionRequest $request, Institucion $institucion): RedirectResponse
    {
        $institucion->update($request->validated());

        return back()->with('success', 'Institución actualizada.');
    }

    public function destroy(Institucion $institucion): RedirectResponse
    {
        $institucion->delete();

        return back()->with('success', 'Institución eliminada.');
    }
}
```

- [ ] **Step 6: Rutas**

En `routes/web.php`:
- Añadir imports al inicio: `use App\Http\Controllers\InstitucionController;`.
- Añadir antes de `require __DIR__.'/settings.php';`:
```php
Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)->only(['index', 'store', 'update', 'destroy']);
});
```

- [ ] **Step 7: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=InstitucionCrudTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Institucion.php app/Http/Requests/InstitucionRequest.php app/Http/Controllers/InstitucionController.php routes/web.php tests/Feature/InstitucionCrudTest.php
git commit -m "feat(instituciones): CRUD backend solo-admin con validacion"
```

---

## Task 2: Backend de Usuarios (controlador, requests, ruta, test con guardas de borrado)

**Files:**
- Create: `app/Http/Requests/StoreUsuarioRequest.php`, `app/Http/Requests/UpdateUsuarioRequest.php`
- Create: `app/Http/Controllers/UsuarioController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/UsuarioCrudTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/UsuarioCrudTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_no_admin_recibe_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())
            ->get('/usuarios')
            ->assertForbidden();
    }

    public function test_admin_crea_usuario_con_password_hasheada(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'Ana', 'apellidos' => 'López', 'email' => 'ana@test.mx',
                'telefono' => null, 'rol' => 'Juez', 'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('email', 'ana@test.mx')->first();
        $this->assertNotNull($user);
        $this->assertSame('Juez', $user->rol->value);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_duplicado_es_rechazado(): void
    {
        User::factory()->create(['email' => 'dup@test.mx']);

        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'X', 'apellidos' => 'Y', 'email' => 'dup@test.mx',
                'rol' => 'Piloto', 'password' => 'secret123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_rol_invalido_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'X', 'apellidos' => 'Y', 'email' => 'z@test.mx',
                'rol' => 'Hacker', 'password' => 'secret123',
            ])
            ->assertSessionHasErrors('rol');
    }

    public function test_editar_sin_password_no_cambia_el_hash(): void
    {
        $user = User::factory()->coach()->create(['password' => Hash::make('original123')]);

        $this->actingAs($this->admin())
            ->put("/usuarios/{$user->id}", [
                'name' => 'Nuevo', 'apellidos' => 'Nombre', 'email' => $user->email,
                'rol' => 'Coach', 'password' => '',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('original123', $user->fresh()->password));
        $this->assertSame('Nuevo', $user->fresh()->name);
    }

    public function test_no_se_puede_borrar_usuario_referenciado(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($this->admin())
            ->delete("/usuarios/{$piloto->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $piloto->id]);
    }

    public function test_no_se_puede_borrar_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/usuarios/{$admin->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_borra_usuario_sin_referencias(): void
    {
        $user = User::factory()->create(['rol' => 'Piloto']);

        $this->actingAs($this->admin())
            ->delete("/usuarios/{$user->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=UsuarioCrudTest`
Expected: FAIL (rutas/controlador inexistentes).

- [ ] **Step 3: Form Requests**

`app/Http/Requests/StoreUsuarioRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
```

`app/Http/Requests/UpdateUsuarioRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('usuario'))],
            'telefono' => ['nullable', 'string', 'max:255'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
```

- [ ] **Step 4: Controlador**

`app/Http/Controllers/UsuarioController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('usuarios/index', [
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'apellidos', 'email', 'telefono', 'rol']),
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create([...$data, 'password' => Hash::make($data['password'])]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return back()->with('success', 'Usuario creado.');
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes eliminar tu propia cuenta.']);
        }

        if ($usuario->robotsComoPiloto()->exists() || $usuario->inspecciones()->exists()) {
            return back()->withErrors(['usuario' => 'No se puede eliminar: el usuario tiene robots o inspecciones asociadas.']);
        }

        $usuario->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
```

- [ ] **Step 5: Rutas**

En `routes/web.php`:
- Añadir import: `use App\Http\Controllers\UsuarioController;`.
- Dentro del grupo admin ya creado en Task 1, añadir:
```php
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 6: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=UsuarioCrudTest`
Expected: PASS (8 tests).

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreUsuarioRequest.php app/Http/Requests/UpdateUsuarioRequest.php app/Http/Controllers/UsuarioController.php routes/web.php tests/Feature/UsuarioCrudTest.php
git commit -m "feat(usuarios): CRUD backend solo-admin con guardas de borrado"
```

---

## Task 3: Wayfinder + tipos frontend + navegación por rol

**Files:**
- Modify: `resources/js/types/navigation.ts`
- Create: `resources/js/types/models.ts`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Regenerar acciones Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: genera/actualiza `resources/js/actions/App/Http/Controllers/InstitucionController.ts` y `UsuarioController.ts` (y rutas). Sin errores.

- [ ] **Step 2: Tipos de modelos**

`resources/js/types/models.ts`:
```ts
export type Institucion = {
    id_institucion: number;
    nombre: string;
    tipo: 'Pública' | 'Privada' | 'Independiente';
    estado: string;
};

export type UsuarioRow = {
    id: number;
    name: string;
    apellidos: string;
    email: string;
    telefono: string | null;
    rol: 'Administrador' | 'Juez' | 'Coach' | 'Piloto';
};
```

En `resources/js/types/index.ts`, añadir al inicio del archivo:
```ts
export type * from './models';
```

- [ ] **Step 3: `NavItem.roles?`**

En `resources/js/types/navigation.ts`:
- Añadir import al inicio: `import type { User } from './auth';`
- Cambiar el tipo `NavItem` añadiendo el campo opcional:
```ts
export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    roles?: Array<User['rol']>;
};
```

- [ ] **Step 4: Sidebar filtrado por rol**

Reemplazar `resources/js/components/app-sidebar.tsx` por:
```tsx
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Building2, FolderGit2, LayoutGrid, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import instituciones from '@/routes/instituciones';
import usuarios from '@/routes/usuarios';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Instituciones',
        href: instituciones.index(),
        icon: Building2,
        roles: ['Administrador'],
    },
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: Users,
        roles: ['Administrador'],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const visibleNavItems = mainNavItems.filter(
        (item) => !item.roles || item.roles.includes(auth.user.rol),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
```
(Nota: `@/routes/instituciones` y `@/routes/usuarios` los genera Wayfinder en el Step 1; exponen `.index()`. Si el import por defecto no expone `index`, usar `import { index as institucionesIndex } from '@/routes/instituciones'` y `institucionesIndex()`.)

- [ ] **Step 5: Verificar build**

Run: `npm run build`
Expected: build exitoso, sin errores TS (confirma que los imports Wayfinder y tipos resuelven).

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/navigation.ts resources/js/types/models.ts resources/js/types/index.ts resources/js/components/app-sidebar.tsx resources/js/actions resources/js/routes
git commit -m "feat(nav): navegacion por rol y tipos de instituciones/usuarios"
```

---

## Task 4: Componente de confirmación de borrado + UI de Instituciones

**Files:**
- Create: `resources/js/components/confirm-delete-dialog.tsx`
- Create: `resources/js/components/instituciones/institucion-form-dialog.tsx`
- Create: `resources/js/pages/instituciones/index.tsx`

- [ ] **Step 1: Diálogo de confirmación reutilizable**

`resources/js/components/confirm-delete-dialog.tsx`:
```tsx
import { useState } from 'react';
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

type ConfirmDeleteDialogProps = {
    trigger: React.ReactNode;
    title: string;
    description: string;
    onConfirm: () => void;
};

export default function ConfirmDeleteDialog({ trigger, title, description, onConfirm }: ConfirmDeleteDialogProps) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            onConfirm();
                            setOpen(false);
                        }}
                    >
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Modal de formulario de institución**

`resources/js/components/instituciones/institucion-form-dialog.tsx`:
```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InstitucionController from '@/actions/App/Http/Controllers/InstitucionController';
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
import type { Institucion } from '@/types';

const TIPOS = ['Pública', 'Privada', 'Independiente'] as const;

type Props = {
    institucion?: Institucion;
    trigger: React.ReactNode;
};

export default function InstitucionFormDialog({ institucion, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(institucion);
    const form = useForm({
        nombre: institucion?.nombre ?? '',
        tipo: institucion?.tipo ?? 'Pública',
        estado: institucion?.estado ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!isEdit) {
                    form.reset();
                }
            },
        };

        if (isEdit && institucion) {
            form.put(InstitucionController.update.url(institucion.id_institucion), options);
        } else {
            form.post(InstitucionController.store.url(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar institución' : 'Nueva institución'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />
                        <InputError message={form.errors.nombre} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo">Tipo</Label>
                        <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v as Institucion['tipo'])}>
                            <SelectTrigger id="tipo">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIPOS.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.tipo} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="estado">Estado</Label>
                        <Input id="estado" value={form.data.estado} onChange={(e) => form.setData('estado', e.target.value)} />
                        <InputError message={form.errors.estado} />
                    </div>
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

- [ ] **Step 3: Página índice de instituciones**

`resources/js/pages/instituciones/index.tsx`:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import InstitucionController from '@/actions/App/Http/Controllers/InstitucionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import InstitucionFormDialog from '@/components/instituciones/institucion-form-dialog';
import { Button } from '@/components/ui/button';
import instituciones from '@/routes/instituciones';
import type { Institucion } from '@/types';

type PageProps = {
    instituciones: Institucion[];
};

export default function InstitucionesIndex() {
    const { instituciones: rows } = usePage<PageProps>().props;

    const destroy = (institucion: Institucion) => {
        router.delete(InstitucionController.destroy.url(institucion.id_institucion), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Instituciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Instituciones</h1>
                    <InstitucionFormDialog trigger={<Button>Nueva institución</Button>} />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Tipo</th>
                                <th scope="col" className="p-3">Estado</th>
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={4}>
                                        No hay instituciones registradas.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((institucion) => (
                                    <tr key={institucion.id_institucion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{institucion.nombre}</td>
                                        <td className="p-3">{institucion.tipo}</td>
                                        <td className="p-3">{institucion.estado}</td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <InstitucionFormDialog
                                                    institucion={institucion}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar institución"
                                                    description={`¿Seguro que deseas eliminar "${institucion.nombre}"? Los robots asociados quedarán sin institución.`}
                                                    onConfirm={() => destroy(institucion)}
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

InstitucionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Instituciones',
            href: instituciones.index(),
        },
    ],
};
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: build exitoso sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/confirm-delete-dialog.tsx resources/js/components/instituciones resources/js/pages/instituciones
git commit -m "feat(instituciones): UI lista + modales de alta/edicion/borrado"
```

---

## Task 5: UI de Usuarios

**Files:**
- Create: `resources/js/components/usuarios/usuario-form-dialog.tsx`
- Create: `resources/js/pages/usuarios/index.tsx`

- [ ] **Step 1: Modal de formulario de usuario**

`resources/js/components/usuarios/usuario-form-dialog.tsx`:
```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
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
import type { UsuarioRow } from '@/types';

const ROLES = ['Administrador', 'Juez', 'Coach', 'Piloto'] as const;

type Props = {
    usuario?: UsuarioRow;
    trigger: React.ReactNode;
};

export default function UsuarioFormDialog({ usuario, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(usuario);
    const form = useForm({
        name: usuario?.name ?? '',
        apellidos: usuario?.apellidos ?? '',
        email: usuario?.email ?? '',
        telefono: usuario?.telefono ?? '',
        rol: usuario?.rol ?? 'Piloto',
        password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('password');
                if (!isEdit) {
                    form.reset();
                }
            },
        };

        if (isEdit && usuario) {
            form.put(UsuarioController.update.url(usuario.id), options);
        } else {
            form.post(UsuarioController.store.url(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="apellidos">Apellidos</Label>
                        <Input id="apellidos" value={form.data.apellidos} onChange={(e) => form.setData('apellidos', e.target.value)} />
                        <InputError message={form.errors.apellidos} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo</Label>
                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="telefono">Teléfono</Label>
                        <Input id="telefono" value={form.data.telefono ?? ''} onChange={(e) => form.setData('telefono', e.target.value)} />
                        <InputError message={form.errors.telefono} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rol">Rol</Label>
                        <Select value={form.data.rol} onValueChange={(v) => form.setData('rol', v as UsuarioRow['rol'])}>
                            <SelectTrigger id="rol">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ROLES.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {r}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.rol} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">{isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña'}</Label>
                        <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" />
                        <InputError message={form.errors.password} />
                    </div>
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

- [ ] **Step 2: Página índice de usuarios**

`resources/js/pages/usuarios/index.tsx`:
```tsx
import { Head, router, usePage } from '@inertiajs/react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import UsuarioFormDialog from '@/components/usuarios/usuario-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import usuarios from '@/routes/usuarios';
import type { UsuarioRow } from '@/types';

type PageProps = {
    usuarios: UsuarioRow[];
};

export default function UsuariosIndex() {
    const { usuarios: rows } = usePage<PageProps>().props;

    const destroy = (usuario: UsuarioRow) => {
        router.delete(UsuarioController.destroy.url(usuario.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Usuarios</h1>
                    <UsuarioFormDialog trigger={<Button>Nuevo usuario</Button>} />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Correo</th>
                                <th scope="col" className="p-3">Teléfono</th>
                                <th scope="col" className="p-3">Rol</th>
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={5}>
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((usuario) => (
                                    <tr key={usuario.id} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{usuario.name} {usuario.apellidos}</td>
                                        <td className="p-3">{usuario.email}</td>
                                        <td className="p-3">{usuario.telefono ?? '—'}</td>
                                        <td className="p-3"><Badge variant="secondary">{usuario.rol}</Badge></td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <UsuarioFormDialog
                                                    usuario={usuario}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar usuario"
                                                    description={`¿Seguro que deseas eliminar a "${usuario.name} ${usuario.apellidos}"?`}
                                                    onConfirm={() => destroy(usuario)}
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

UsuariosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: usuarios.index(),
        },
    ],
};
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: build exitoso sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/usuarios resources/js/pages/usuarios
git commit -m "feat(usuarios): UI lista + modales de alta/edicion/borrado"
```

---

## Task 6: Verificación integral de la Fase 2.1a

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa de pruebas**

Run: `php artisan test --compact`
Expected: todas PASS (67 previos + InstitucionCrudTest 6 + UsuarioCrudTest 8 = 81).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(participantes): verificacion integral Fase 2.1a" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Autorización solo-Administrador (rutas en grupo `role:Administrador`) + tests 403 → Tasks 1,2 ✓
- CRUD Instituciones (index/store/update/destroy) + binding `id_institucion` + borrado deja robot NULL → Task 1 ✓
- CRUD Usuarios + password hasheada al crear / opcional al editar + email único + rol enum → Task 2 ✓
- Borrado seguro de usuarios (auto-borrado y referencias) → Task 2 ✓
- Form Requests con validación (tipo in, rol enum, email unique ignore) → Tasks 1,2 ✓
- UI lista + modales (Dialog + useForm) + confirmación de borrado → Tasks 4,5 ✓
- Navegación por rol (NavItem.roles + filtro) → Task 3 ✓
- Tipos frontend (Institucion, UsuarioRow) + Wayfinder → Task 3 ✓
- Pruebas feature (auth, CRUD, validación, guardas) con assertInertia/assertSessionHasErrors → Tasks 1,2 ✓
- DoD: suite 100%, pint, build → Task 6 ✓

**Riesgo conocido (Wayfinder):** los imports `@/routes/instituciones`, `@/routes/usuarios` y `@/actions/.../{Institucion,Usuario}Controller` solo existen tras `php artisan wayfinder:generate` (Task 3 Step 1), que debe correrse antes de tocar el frontend. La forma exacta de `update.url(id)` (escalar vs objeto) puede variar; si `npm run build` arroja un error de tipo en esa llamada, pasar el parámetro como objeto `{ institucion: id }` / `{ usuario: id }` según la firma generada.
