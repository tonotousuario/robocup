# RoboLeague — Sistema de tablas (DataTable) · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) o superpowers:executing-plans. REQUIRED para tareas de UI: usar la skill **frontend-design**. Los pasos usan checkbox (`- [ ]`).

**Goal:** Búsqueda (debounce), orden por columna y paginación server-side, más filtros clave, en los 4 índices, vía un `DataTable` reutilizable.

**Architecture:** Componente `DataTable` + hook `useTableQuery` (visitas parciales Inertia con query params). Cada `index` pasa a `->paginate(15)->withQueryString()->through()` con búsqueda/orden(lista blanca)/filtros por query param, conservando scope/autorización. Se hace Inscripciones primero (patrón completo) y se replica.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4 (Eléctrico), shadcn (Input/Select/Button/Badge), lucide-react, PHPUnit 12, Pint, Playwright MCP.

**Convenciones/contexto (verificado):**
- Baseline: 178 pruebas. Primitivas de A en `main`: `EmptyState`, `PageHeader`, `estadoBadgeVariant` (`@/lib/utils`), `Badge`. shadcn `Select` en `@/components/ui/select`, `Input` en `@/components/ui/input`.
- Índices hoy (`->get()->map()`): `InscripcionController@index` (scope no-admin por `whereHas('robot', id_piloto)`; filas {id_inscripcion,robot,categoria,piloto,tarifa,monto_pagado,estado_pago}; props extra robotsInscribibles/tarifaVigente), `RobotController@index` (scope no-admin `where id_piloto`; filas {id_robot,nombre,categoria,institucion,piloto,id_piloto}; props categorias/instituciones/pilotos), `UsuarioController@index` (`User::orderBy('name')->get([...])`), `InstitucionController@index` (`Institucion::orderBy('nombre')->get()`).
- Inertia `->through()` sobre paginador preserva `{data, links, meta}`. `withQueryString()` mantiene q/sort/dir/filtros en links.
- Páginas React existentes ya tienen diálogos/acciones (no romperlos). `router` de `@inertiajs/react`.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Frontend gate `npm run build`. Tests `php artisan test --compact --filter=...`. Dev server Playwright en :8000.

---

## File Structure

**Frontend (reutilizable):**
- Create: `resources/js/components/data-table/data-table.tsx`
- Create: `resources/js/hooks/use-table-query.ts`
- Create: `resources/js/types/pagination.ts` (tipos `Paginated<T>`, `PageLink`)

**Backend + páginas (por índice):**
- Modify: `app/Http/Controllers/InscripcionController.php` + `resources/js/pages/inscripciones/index.tsx`
- Modify: `app/Http/Controllers/RobotController.php` + `resources/js/pages/robots/index.tsx`
- Modify: `app/Http/Controllers/UsuarioController.php` + `resources/js/pages/usuarios/index.tsx`
- Modify: `app/Http/Controllers/InstitucionController.php` + `resources/js/pages/instituciones/index.tsx`

**Tests:** extender `tests/Feature/InscripcionControllerTest.php`, `RobotControllerTest.php`, `UsuarioControllerTest.php`, `InstitucionControllerTest.php` (o los nombres existentes; verificar).

---

## Task 0: Rama

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-ux-tablas
```
Expected: `Switched to a new branch 'feature/roboleague-ux-tablas'`.

---

## Task 1: Tipos de paginación + hook `useTableQuery` + `DataTable`

**REQUIRED SUB-SKILL:** usar **frontend-design** para el diseño visual del `DataTable` (cabeceras, hover, densidad, paginación, indicador de orden), sobre el tema Eléctrico.

**Files:**
- Create: `resources/js/types/pagination.ts`
- Create: `resources/js/hooks/use-table-query.ts`
- Create: `resources/js/components/data-table/data-table.tsx`

- [ ] **Step 1: Tipos de paginación**

`resources/js/types/pagination.ts`:
```ts
export type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PageLink[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};
```
(Nota: Inertia serializa el paginador de Laravel como `{data, links, meta}` cuando se usa un Resource o `->through()`. Si en este proyecto el paginador serializa `links`/`meta` en el nivel raíz en vez de anidado, ajustar el tipo al shape real observado tras el primer `dd`/captura — ver Task 2 Step 4.)

- [ ] **Step 2: Hook `useTableQuery`**

`resources/js/hooks/use-table-query.ts`:
```ts
import { router } from '@inertiajs/react';
import { useCallback, useRef } from 'react';

type Params = Record<string, string | number | undefined>;

/**
 * Centraliza visitas parciales para tablas: preserva los demás query params
 * al cambiar uno; `q` se aplica con debounce.
 */
export function useTableQuery(routeUrl: string, only: string[], current: Params) {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const visit = useCallback(
        (next: Params) => {
            const merged: Params = { ...current, ...next };
            Object.keys(merged).forEach((k) => {
                if (merged[k] === undefined || merged[k] === '' || merged[k] === 'todos') {
                    delete merged[k];
                }
            });
            router.get(routeUrl, merged, { preserveState: true, preserveScroll: true, replace: true, only });
        },
        [routeUrl, only, current],
    );

    const setFiltro = useCallback((key: string, value: string | number | undefined) => visit({ [key]: value, page: undefined }), [visit]);

    const setBusqueda = useCallback(
        (value: string) => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
            timer.current = setTimeout(() => visit({ q: value, page: undefined }), 300);
        },
        [visit],
    );

    const setOrden = useCallback(
        (sort: string, currentSort?: string, currentDir?: string) => {
            const dir = currentSort === sort && currentDir === 'asc' ? 'desc' : 'asc';
            visit({ sort, dir, page: undefined });
        },
        [visit],
    );

    return { setFiltro, setBusqueda, setOrden };
}
```

- [ ] **Step 3: Componente `DataTable`**

`resources/js/components/data-table/data-table.tsx`:
```tsx
import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronsUpDown, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';

export type Column<T> = {
    key: string;
    header: string;
    sortable?: boolean;
    render?: (row: T) => ReactNode;
    className?: string;
};

type DataTableProps<T> = {
    columns: Column<T>[];
    page: Paginated<T>;
    rowKey: (row: T) => string | number;
    sort?: string;
    dir?: string;
    onSort: (key: string) => void;
    toolbar?: ReactNode;
    empty: { icon: LucideIcon; title: string; description?: string };
};

export default function DataTable<T>({ columns, page, rowKey, sort, dir, onSort, toolbar, empty }: DataTableProps<T>) {
    return (
        <div className="flex flex-col gap-4">
            {toolbar}

            {page.data.length === 0 ? (
                <EmptyState icon={empty.icon} title={empty.title} description={empty.description} />
            ) : (
                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border">
                            <tr>
                                {columns.map((col) => (
                                    <th key={col.key} className={cn('p-3 font-medium', col.className)}>
                                        {col.sortable ? (
                                            <button
                                                type="button"
                                                onClick={() => onSort(col.key)}
                                                className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                                            >
                                                {col.header}
                                                {sort === col.key ? (
                                                    dir === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />
                                                ) : (
                                                    <ChevronsUpDown className="size-3.5 opacity-50" />
                                                )}
                                            </button>
                                        ) : (
                                            col.header
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((row) => (
                                <tr key={rowKey(row)} className="border-b border-sidebar-border/40 transition-colors last:border-0 hover:bg-muted/40">
                                    {columns.map((col) => (
                                        <td key={col.key} className={cn('p-3', col.className)}>
                                            {col.render ? col.render(row) : (row as Record<string, ReactNode>)[col.key]}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {page.meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {page.meta.from}–{page.meta.to} de {page.meta.total}
                    </span>
                    <div className="flex gap-1">
                        {page.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                className={cn(
                                    'rounded-md px-3 py-1.5',
                                    link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                                    !link.url && 'pointer-events-none opacity-50',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso (componentes compilan aunque sin uso aún).

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/pagination.ts resources/js/hooks/use-table-query.ts resources/js/components/data-table/data-table.tsx
git commit -m "feat(ui): DataTable reutilizable + useTableQuery + tipos de paginacion"
```

---

## Task 2: Inscripciones — backend paginado/búsqueda/orden/filtros + tests

**Files:**
- Modify: `app/Http/Controllers/InscripcionController.php`
- Test: `tests/Feature/InscripcionControllerTest.php` (o el test existente de inscripciones; verificar nombre con `ls tests/Feature | grep -i inscrip`)

- [ ] **Step 1: Añadir tests que fallan**

Verificar el archivo de test existente de inscripciones y añadir (con los `use` que ya tenga; necesita `Categoria`, `Robot`, `Inscripcion`, `User`, `Tarifa`, `AssertableInertia as Assert`). Crear un helper local si no existe para una inscripción de un robot con nombre dado:
```php
public function test_index_pagina_y_busca(): void
{
    $admin = User::factory()->create(['rol' => 'Administrador']);
    $cat = Categoria::factory()->combate()->create();
    $tarifa = Tarifa::factory()->create();
    foreach (['Tanque', 'Sierra', 'Martillo'] as $n) {
        $robot = Robot::factory()->create(['id_categoria' => $cat->id_categoria, 'nombre' => $n]);
        Inscripcion::factory()->create(['id_robot' => $robot->id_robot, 'id_tarifa' => $tarifa->id_tarifa, 'estado_pago' => 'Pagado']);
    }

    // estructura paginada
    $this->actingAs($admin)->get('/inscripciones')
        ->assertInertia(fn (Assert $p) => $p->has('inscripciones.data')->where('inscripciones.meta.per_page', 15));

    // búsqueda por nombre de robot (relación)
    $this->actingAs($admin)->get('/inscripciones?q=Sierra')
        ->assertInertia(fn (Assert $p) => $p->has('inscripciones.data', 1)->where('inscripciones.data.0.robot', 'Sierra'));
}

public function test_index_filtra_por_estado_y_ordena(): void
{
    $admin = User::factory()->create(['rol' => 'Administrador']);
    $cat = Categoria::factory()->combate()->create();
    $tarifa = Tarifa::factory()->create();
    $r1 = Robot::factory()->create(['id_categoria' => $cat->id_categoria, 'nombre' => 'A']);
    $r2 = Robot::factory()->create(['id_categoria' => $cat->id_categoria, 'nombre' => 'B']);
    Inscripcion::factory()->create(['id_robot' => $r1->id_robot, 'id_tarifa' => $tarifa->id_tarifa, 'estado_pago' => 'Pagado', 'monto_pagado' => 100]);
    Inscripcion::factory()->create(['id_robot' => $r2->id_robot, 'id_tarifa' => $tarifa->id_tarifa, 'estado_pago' => 'Pendiente', 'monto_pagado' => 200]);

    // filtro por estado
    $this->actingAs($admin)->get('/inscripciones?estado=Pendiente')
        ->assertInertia(fn (Assert $p) => $p->has('inscripciones.data', 1)->where('inscripciones.data.0.estado_pago', 'Pendiente'));

    // orden por monto asc (columna permitida)
    $this->actingAs($admin)->get('/inscripciones?sort=monto_pagado&dir=asc')
        ->assertInertia(fn (Assert $p) => $p->where('inscripciones.data.0.monto_pagado', fn ($m) => (float) $m === 100.0));

    // columna no permitida → ignorada (sin error 500)
    $this->actingAs($admin)->get('/inscripciones?sort=robot&dir=asc')->assertOk();
}
```
(Ajustar factories a las reales del proyecto; `Tarifa::factory()` debe existir — si no, crear tarifa como en otros tests.)

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=Inscripcion`
Expected: FAIL (hoy devuelve colección plana sin meta/filtros).

- [ ] **Step 3: Reescribir `index` con paginación/búsqueda/orden/filtros**

En `app/Http/Controllers/InscripcionController.php`, reemplazar la construcción de `$inscripciones` por:
```php
        $query = Inscripcion::with(['robot.piloto', 'robot.categoria', 'tarifa']);

        if (! $user->isAdministrador()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->whereHas('robot', fn ($r) => $r->where('nombre', 'ilike', "%{$q}%")
                ->orWhereHas('piloto', fn ($p) => $p->where('name', 'ilike', "%{$q}%")->orWhere('apellidos', 'ilike', "%{$q}%"))
                ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'ilike', "%{$q}%")));
        }

        if ($request->filled('estado')) {
            $query->where('estado_pago', $request->string('estado')->toString());
        }

        if ($request->filled('categoria')) {
            $query->whereHas('robot', fn ($r) => $r->where('id_categoria', $request->integer('categoria')));
        }

        $ordenables = ['id_inscripcion', 'estado_pago', 'monto_pagado'];
        $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'id_inscripcion';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $inscripciones = $query->paginate(15)->withQueryString()->through(fn (Inscripcion $i) => [
            'id_inscripcion' => $i->id_inscripcion,
            'robot' => $i->robot?->nombre,
            'categoria' => $i->robot?->categoria?->nombre,
            'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
            'tarifa' => $i->tarifa?->descripcion,
            'monto_pagado' => $i->monto_pagado,
            'estado_pago' => $i->estado_pago,
        ]);
```
Y en el array de `Inertia::render('inscripciones/index', [...])`, añadir los datos de filtro y estado actual:
```php
            'inscripciones' => $inscripciones,
            'categorias' => \App\Models\Categoria::orderBy('nombre')->get(['id_categoria', 'nombre']),
            'filtros' => [
                'q' => $request->query('q', ''),
                'estado' => $request->query('estado', ''),
                'categoria' => $request->query('categoria', ''),
                'sort' => $sort,
                'dir' => $dir,
            ],
```
(Conservar `robotsInscribibles` y `tarifaVigente` existentes.)

- [ ] **Step 4: Ejecutar y confirmar el shape paginado**

Run: `php artisan test --compact --filter=Inscripcion`
Expected: PASS. Si los asserts de `inscripciones.data`/`inscripciones.meta` fallan por shape (Inertia podría anidar distinto), inspeccionar el JSON real (añadir temporal `->dump()` en el test o revisar la respuesta) y ajustar el tipo `Paginated` (Task 1 Step 1) + los asserts al shape real. NO forzar verde debilitando; ajustar al shape correcto.

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/InscripcionController.php tests/Feature/InscripcionControllerTest.php
git commit -m "feat(inscripciones): paginacion, busqueda, orden y filtros server-side"
```

---

## Task 3: Inscripciones — adoptar `DataTable` en la página

**REQUIRED SUB-SKILL:** **frontend-design** para la composición (toolbar + tabla).

**Files:**
- Modify: `resources/js/pages/inscripciones/index.tsx`

- [ ] **Step 1: Cambiar props a paginado + columnas + toolbar**

En `resources/js/pages/inscripciones/index.tsx`:
- Cambiar el tipo `inscripciones: InscripcionRow[]` a `inscripciones: Paginated<InscripcionRow>` (import `type { Paginated } from '@/types/pagination'`). Añadir `categorias: { id_categoria: number; nombre: string }[]` y `filtros: { q: string; estado: string; categoria: string; sort: string; dir: string }`.
- Importar `DataTable, { type Column }` de `@/components/data-table/data-table`, `useTableQuery` de `@/hooks/use-table-query`, `Input`/`Select...` de ui, `estadoBadgeVariant` de `@/lib/utils`, `PageHeader`, e iconos lucide (`ClipboardList`).
- Construir `const { setBusqueda, setFiltro, setOrden } = useTableQuery(inscripciones.url... )`. Para `routeUrl` usar `inscripciones.index().url` (Wayfinder) o `/inscripciones`. `only: ['inscripciones', 'filtros']`. `current` = `filtros`.
- Definir columnas (robot, categoria, piloto, tarifa, monto_pagado, estado_pago con `Badge variant={estadoBadgeVariant(row.estado_pago)}`, y la columna de Acciones con los botones Pagar/Cancelar/eliminar existentes — reusar las funciones `pagar`/`cancelar` y los diálogos ya presentes). Marcar `sortable: true` en `estado_pago` y `monto_pagado` (y id si se desea).
- Toolbar: `<Input>` de búsqueda (defaultValue `filtros.q`, onChange → `setBusqueda`) + `<Select>` de estado (Todos/Pendiente/Pagado/Cancelado → `setFiltro('estado', v)`) + `<Select>` de categoría (Todas + categorias → `setFiltro('categoria', v)`). El botón "Inscribir robot" (dialog existente) va en `PageHeader.action`.
- Render: `<PageHeader title="Inscripciones" action={<InscribirRobotDialog .../>} />` + `<DataTable columns={columns} page={inscripciones} rowKey={(r)=>r.id_inscripcion} sort={filtros.sort} dir={filtros.dir} onSort={(k)=>setOrden(k, filtros.sort, filtros.dir)} toolbar={...} empty={{icon: ClipboardList, title:'Sin inscripciones', description:'Aún no hay inscripciones.'}} />`.
- Mantener intactos los diálogos/acciones (`InscribirRobotDialog`, `ConfirmDeleteDialog`, `pagar`, `cancelar`). Quitar el `ESTADO_CLASS` manual si se reemplaza por `estadoBadgeVariant` (o conservarlo si se prefiere su paleta — decisión del implementador, pero consistente con A: usar `estadoBadgeVariant`).

- [ ] **Step 2: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/inscripciones/index.tsx
git commit -m "feat(inscripciones): adoptar DataTable con busqueda, orden y filtros"
```

---

## Task 4: Robots — backend + página

**Files:**
- Modify: `app/Http/Controllers/RobotController.php`
- Modify: `resources/js/pages/robots/index.tsx`
- Test: el test de robots existente (`ls tests/Feature | grep -i robot`)

- [ ] **Step 1: Tests (búsqueda por nombre, filtro categoría, paginación, orden)**

Añadir al test de robots:
```php
public function test_index_robots_pagina_busca_y_filtra(): void
{
    $admin = User::factory()->create(['rol' => 'Administrador']);
    $cat = Categoria::factory()->combate()->create();
    Robot::factory()->create(['nombre' => 'Tanque', 'id_categoria' => $cat->id_categoria]);
    Robot::factory()->create(['nombre' => 'Sierra', 'id_categoria' => $cat->id_categoria]);

    $this->actingAs($admin)->get('/robots')
        ->assertInertia(fn (Assert $p) => $p->has('robots.data')->where('robots.meta.per_page', 15));

    $this->actingAs($admin)->get('/robots?q=Sierra')
        ->assertInertia(fn (Assert $p) => $p->has('robots.data', 1)->where('robots.data.0.nombre', 'Sierra'));

    $this->actingAs($admin)->get('/robots?categoria='.$cat->id_categoria)
        ->assertInertia(fn (Assert $p) => $p->has('robots.data', 2));

    $this->actingAs($admin)->get('/robots?sort=nombre&dir=asc')
        ->assertInertia(fn (Assert $p) => $p->where('robots.data.0.nombre', 'Sierra'));
}
```

- [ ] **Step 2: Ejecutar (debe fallar)** — `php artisan test --compact --filter=Robot` → FAIL.

- [ ] **Step 3: Reescribir `RobotController@index`**

Reemplazar la construcción de `$robots` por (conservando scope y las props `categorias`/`instituciones`/`pilotos`):
```php
        if ($request->filled('q')) {
            $query->where('nombre', 'ilike', '%'.$request->string('q')->toString().'%');
        }
        if ($request->filled('categoria')) {
            $query->where('id_categoria', $request->integer('categoria'));
        }

        $ordenables = ['nombre'];
        $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'nombre';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $query->reorder($sort, $dir);

        $robots = $query->paginate(15)->withQueryString()->through(fn (Robot $robot) => [
            'id_robot' => $robot->id_robot,
            'nombre' => $robot->nombre,
            'categoria' => $robot->categoria?->nombre,
            'institucion' => $robot->institucion?->nombre,
            'piloto' => $robot->piloto ? $robot->piloto->name.' '.$robot->piloto->apellidos : null,
            'id_piloto' => $robot->id_piloto,
        ]);
```
(`reorder` reemplaza el `orderBy('nombre')` inicial del query.) Añadir al render un `'filtros' => ['q' => $request->query('q',''), 'categoria' => $request->query('categoria',''), 'sort' => $sort, 'dir' => $dir]`.

- [ ] **Step 4: Página robots adopta DataTable**

En `resources/js/pages/robots/index.tsx`: cambiar `robots` a `Paginated<RobotRow>`, añadir `filtros`; columnas (nombre sortable, categoria, institucion, piloto, acciones existentes); toolbar con búsqueda + Select de categoría; `PageHeader` con la acción de crear robot existente; `EmptyState` (icon `Bot`). Mantener diálogos/acciones existentes.

- [ ] **Step 5: Build + tests + commit**

```bash
npm run build && php artisan test --compact --filter=Robot
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/RobotController.php resources/js/pages/robots/index.tsx tests/Feature/RobotControllerTest.php
git commit -m "feat(robots): DataTable con busqueda, filtro de categoria, orden y paginacion"
```

---

## Task 5: Usuarios — backend + página

**Files:**
- Modify: `app/Http/Controllers/UsuarioController.php`
- Modify: `resources/js/pages/usuarios/index.tsx`
- Test: test de usuarios existente (`ls tests/Feature | grep -i usuario`)

- [ ] **Step 1: Tests (búsqueda nombre/email, filtro rol, paginación)**

```php
public function test_index_usuarios_pagina_busca_y_filtra_por_rol(): void
{
    $admin = User::factory()->create(['rol' => 'Administrador', 'name' => 'Zoe']);
    User::factory()->juez()->create(['name' => 'JuezUno']);

    $this->actingAs($admin)->get('/usuarios')
        ->assertInertia(fn (Assert $p) => $p->has('usuarios.data')->where('usuarios.meta.per_page', 15));

    $this->actingAs($admin)->get('/usuarios?q=JuezUno')
        ->assertInertia(fn (Assert $p) => $p->has('usuarios.data', 1)->where('usuarios.data.0.name', 'JuezUno'));

    $this->actingAs($admin)->get('/usuarios?rol=Juez')
        ->assertInertia(fn (Assert $p) => $p->where('usuarios.data', fn ($d) => collect($d)->every(fn ($u) => $u['rol'] === 'Juez')));
}
```
(Verificar autorización: `/usuarios` suele ser solo Admin; usar admin en los tests. Si la ruta exige Admin, los no-admin siguen 403 — no cambia.)

- [ ] **Step 2: Ejecutar (debe fallar)** — `php artisan test --compact --filter=Usuario`.

- [ ] **Step 3: Reescribir `UsuarioController@index`**

```php
public function index(Request $request): Response
{
    $query = User::query();

    if ($request->filled('q')) {
        $q = $request->string('q')->toString();
        $query->where(fn ($w) => $w->where('name', 'ilike', "%{$q}%")
            ->orWhere('apellidos', 'ilike', "%{$q}%")
            ->orWhere('email', 'ilike', "%{$q}%"));
    }
    if ($request->filled('rol')) {
        $query->where('rol', $request->string('rol')->toString());
    }

    $ordenables = ['name', 'email', 'rol'];
    $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'name';
    $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

    $usuarios = $query->orderBy($sort, $dir)
        ->paginate(15)
        ->withQueryString()
        ->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'apellidos' => $u->apellidos,
            'email' => $u->email,
            'telefono' => $u->telefono,
            'rol' => $u->rol,
        ]);

    return Inertia::render('usuarios/index', [
        'usuarios' => $usuarios,
        'filtros' => ['q' => $request->query('q', ''), 'rol' => $request->query('rol', ''), 'sort' => $sort, 'dir' => $dir],
    ]);
}
```
(Añadir `use Illuminate\Http\Request;` si falta. `rol` puede castear a enum en el modelo; el `->through()` lo serializa igual que hoy — verificar que el shape de `rol` no cambie respecto al actual.)

- [ ] **Step 4: Página usuarios adopta DataTable**

Columnas: name (sortable), apellidos, email (sortable), telefono, rol (sortable, Badge), acciones existentes (si las hay). Toolbar: búsqueda + Select de rol (Administrador/Juez/Coach/Piloto). PageHeader con acción de crear usuario si existe. EmptyState (icon `Users`).

- [ ] **Step 5: Build + tests + commit**

```bash
npm run build && php artisan test --compact --filter=Usuario
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/UsuarioController.php resources/js/pages/usuarios/index.tsx tests/Feature/UsuarioControllerTest.php
git commit -m "feat(usuarios): DataTable con busqueda, filtro de rol, orden y paginacion"
```

---

## Task 6: Instituciones — backend + página

**Files:**
- Modify: `app/Http/Controllers/InstitucionController.php`
- Modify: `resources/js/pages/instituciones/index.tsx`
- Test: test de instituciones existente (`ls tests/Feature | grep -i institu`)

- [ ] **Step 1: Tests (búsqueda + paginación + orden por nombre)**

```php
public function test_index_instituciones_pagina_y_busca(): void
{
    $admin = User::factory()->create(['rol' => 'Administrador']);
    Institucion::factory()->create(['nombre' => 'Tec Norte']);
    Institucion::factory()->create(['nombre' => 'Uni Sur']);

    $this->actingAs($admin)->get('/instituciones')
        ->assertInertia(fn (Assert $p) => $p->has('instituciones.data')->where('instituciones.meta.per_page', 15));

    $this->actingAs($admin)->get('/instituciones?q=Norte')
        ->assertInertia(fn (Assert $p) => $p->has('instituciones.data', 1)->where('instituciones.data.0.nombre', 'Tec Norte'));
}
```
(Si `Institucion::factory` no existe, crear vía `Institucion::create([...])`. Verificar autorización de la ruta.)

- [ ] **Step 2: Ejecutar (debe fallar)** — `php artisan test --compact --filter=Institu`.

- [ ] **Step 3: Reescribir `InstitucionController@index`**

```php
public function index(Request $request): Response
{
    $query = Institucion::query();

    if ($request->filled('q')) {
        $query->where('nombre', 'ilike', '%'.$request->string('q')->toString().'%');
    }

    $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
    $sort = $request->query('sort') === 'nombre' ? 'nombre' : 'nombre';

    $instituciones = $query->orderBy($sort, $dir)->paginate(15)->withQueryString();

    return Inertia::render('instituciones/index', [
        'instituciones' => $instituciones,
        'filtros' => ['q' => $request->query('q', ''), 'sort' => 'nombre', 'dir' => $dir],
    ]);
}
```
(Añadir `use Illuminate\Http\Request;`. Aquí no hace falta `->through()` porque `Institucion::get()` ya devolvía el modelo completo; `paginate()` serializa el modelo igual. Si la página consume campos específicos, mantener el modelo tal cual.)

- [ ] **Step 4: Página instituciones adopta DataTable**

Columnas según los campos que ya muestra la página (nombre sortable, + las demás columnas actuales). Toolbar: solo búsqueda. PageHeader con la acción de crear institución existente. EmptyState (icon `Building2`). Cambiar el tipo a `Paginated<Institucion>`.

- [ ] **Step 5: Build + tests + commit**

```bash
npm run build && php artisan test --compact --filter=Institu
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/InstitucionController.php resources/js/pages/instituciones/index.tsx tests/Feature/InstitucionControllerTest.php
git commit -m "feat(instituciones): DataTable con busqueda, orden y paginacion"
```

---

## Task 7: Verificación integral + visual (Playwright)

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (reportar total; 178 baseline + nuevos por índice).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual con Playwright**

Con dev server en :8000, login admin@roboleague.test/password:
- `/inscripciones`: capturar; escribir "Sierra" en búsqueda → la tabla filtra (~tras 300 ms); cambiar Select de estado → filtra; click en cabecera "Monto" → ordena (▲▼); usar paginación si hay >15. Captura del estado vacío forzando un filtro sin resultados.
- `/robots`, `/usuarios`, `/instituciones`: captura rápida confirmando toolbar + tabla + paginación coherentes.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(tablas): verificacion visual y ajustes" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `DataTable` + `useTableQuery` + tipos paginación → Task 1 ✓
- Paginación server-side `->paginate(15)->withQueryString()->through()` en los 4 índices → Tasks 2,4,5,6 ✓
- Búsqueda debounce 300 ms (cliente) + `?q=` (backend, incluye relación en Inscripciones/Robots) → Task 1 (hook) + 2/4/5/6 ✓
- Orden con lista blanca; columna no permitida ignorada → Tasks 2/4/5/6 (tests del caso inválido en Inscripciones) ✓
- Filtros clave: estado+categoría (Inscripciones), categoría (Robots), rol (Usuarios), solo búsqueda (Instituciones) → Tasks 2/4/5/6 ✓
- Scope/autorización intactos → se conservan los `authorize`/scope existentes (Inscripciones/Robots) ✓
- PageHeader/EmptyState/badges → Tasks 3/4/5/6 ✓
- Verificación visual Playwright → Task 7 ✓
- frontend-design en tareas de UI (1,3,4,5,6) ✓

**Notas/riesgos:**
- **Shape del paginador Inertia**: el tipo `Paginated` asume `{data, links, meta}`. Laravel `paginate()` serializa con `meta`/`links` anidados al usar API Resources, pero un paginador plano serializa `data` + `links` (array) + campos meta en la raíz (`current_page`, etc.). Task 2 Step 4 obliga a confirmar el shape real y alinear tipo+asserts ANTES de replicar (clave para no arrastrar el error a 4 páginas).
- **`ilike`** es de PostgreSQL (búsqueda case-insensitive) — correcto para este proyecto (pgsql).
- **Filtros de orden por relación**: NO se permiten (solo columnas propias); buscar por relación sí. Documentado en listas blancas.
- **Páginas con diálogos existentes**: cada Task de página debe conservar los diálogos/acciones actuales (crear/editar/eliminar/pagar/cancelar); el implementador lee el archivo antes de reescribir.
- **Tests existentes de cada índice**: verificar que no asuman array plano; si lo hacen (p. ej. `->has('inscripciones', N)`), actualizarlos al shape paginado (`inscripciones.data`).
- Sin migración; no requiere `migrate` en dev.
