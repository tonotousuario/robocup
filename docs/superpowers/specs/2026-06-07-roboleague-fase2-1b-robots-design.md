# RoboLeague — Fase 2.1b: CRUD de Robots (Diseño)

**Fecha:** 2026-06-07
**Fase padre:** Fase 2 → sub-proyecto 2.1 Participantes → **2.1b (Robots)**. (2.1a Instituciones+Usuarios ya está en `main`.)
**Alcance:** Únicamente el CRUD de Robots con autorización por propiedad. Inscripciones/inspección/competencia son fases posteriores.

## Contexto

En `main`: Fase 1 (datos), Fase 2.0 (roles `RolUsuario`, middleware `role`, dashboard), Fase 2.1a (CRUD Instituciones + Usuarios). Patrón CRUD ya establecido y reutilizable:
- Controlador resourceful `only(['index','store','update','destroy'])` + Form Request + Wayfinder + página índice React con tabla + `*-form-dialog.tsx` (modal `useForm`) + `ConfirmDeleteDialog` reutilizable (`resources/js/components/confirm-delete-dialog.tsx`).
- Navegación por rol: `NavItem.roles?` + filtro en `app-sidebar.tsx` por `auth.user.rol`.
- Tipos en `resources/js/types/models.ts` (ya tiene `Institucion`, `UsuarioRow`).
- Modelo `Robot` (Fase 1): tabla `robots`, PK `id_robot`, `nombre`, FK `id_piloto`→users (No Action), `id_institucion`→instituciones (Set Null, nullable), `id_categoria`→categorias (No Action). Relaciones `piloto()`, `institucion()`, `categoria()`.

## Decisiones de diseño

- **Propiedad por `id_piloto`** (el esquema no tiene enlace a coach). Administrador gestiona todos; Piloto solo los suyos; Juez/Coach sin acceso.
- Se usa una **Policy** (`RobotPolicy`) en lugar de solo middleware de rol, porque la autorización depende del registro (propiedad).
- Al **crear/editar como Piloto**, `id_piloto` se fuerza al usuario autenticado (no puede elegir otro). Como Admin, `id_piloto` se elige de la lista de usuarios con rol Piloto.
- UI lista + modales (igual que 2.1a).

## Backend

### `RobotPolicy` (`app/Policies/RobotPolicy.php`)
- `before(User $user, string $ability): ?bool` → `return $user->isAdministrador() ? true : null;` (Admin pasa todo; los demás siguen a los métodos).
- `viewAny(User $user): bool` → `return $user->isPiloto();`
- `create(User $user): bool` → `return $user->isPiloto();`
- `update(User $user, Robot $robot): bool` → `return $user->isPiloto() && $robot->id_piloto === $user->id;`
- `delete(User $user, Robot $robot): bool` → `return $user->isPiloto() && $robot->id_piloto === $user->id;`
(`view` no se usa porque no hay página show; `authorizeResource` sin esos métodos requiere definirlos o limitar el mapeo — ver controlador.)

Registro: Laravel 11+ auto-descubre policies por convención (`App\Models\Robot` → `App\Policies\RobotPolicy`). No requiere registro manual.

### `RobotController`
- `__construct()`: `$this->authorizeResource(Robot::class, 'robot');` (mapea index→viewAny, store→create, update→update, destroy→delete).
- `index()`:
  - `$user = $request->user();`
  - Robots: Admin → `Robot::with(['piloto','institucion','categoria'])->orderBy('nombre')->get()`; Piloto → igual pero `->where('id_piloto', $user->id)`.
  - Mapear a filas planas: `id_robot, nombre, categoria (nombre), institucion (nombre|null), piloto (name+apellidos), id_piloto`.
  - Props: `robots`, `categorias` (`Categoria::orderBy('nombre')->get(['id_categoria','nombre'])`), `instituciones` (`Institucion::orderBy('nombre')->get(['id_institucion','nombre'])`), y `pilotos` solo si Admin (`User::where('rol','Piloto')->orderBy('name')->get(['id','name','apellidos'])`), si no `[]`.
  - Render `robots/index`.
- `store(RobotRequest $request)`: `$data = $request->validated();` si Piloto → `$data['id_piloto'] = $request->user()->id;`. `Robot::create($data);` → `back()->with('success', ...)`.
- `update(RobotRequest $request, Robot $robot)`: igual forzado de `id_piloto` para Piloto; `$robot->update($data);`.
- `destroy(Robot $robot)`: `$robot->delete();` (Robot no tiene dependientes que bloqueen en esta fase; inscripciones se borran en cascada si existieran).

### `RobotRequest` (`app/Http/Requests/RobotRequest.php`)
- `authorize(): bool` → true (la policy/`authorizeResource` ya autoriza).
- `rules()`:
  - `nombre` → required|string|max:255.
  - `id_categoria` → required|integer|exists:categorias,id_categoria.
  - `id_institucion` → nullable|integer|exists:instituciones,id_institucion.
  - `id_piloto` → si el actor es Admin: `['required','integer', Rule::exists('users','id')->where('rol','Piloto')]`; si es Piloto: `['nullable']` (se ignora y se fuerza en el controlador).
  - La condición se resuelve en `rules()` leyendo `$this->user()->isAdministrador()`.

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `RobotRow`: `{ id_robot:number; nombre:string; categoria:string; institucion:string|null; piloto:string; id_piloto:number }`.
- `OpcionCatalogo`: genérico para selects de catálogo, p.ej. `{ id:number; nombre:string }` para categorías/instituciones, y `{ id:number; nombre:string }` para pilotos (nombre = "name apellidos"). Definir `CategoriaOpcion {id_categoria, nombre}`, `InstitucionOpcion {id_institucion, nombre}`, `PilotoOpcion {id, nombre}`.

### Páginas / componentes
- `resources/js/pages/robots/index.tsx`: tabla (Nombre, Categoría, Institución, Piloto, Acciones). Si `auth.user.rol === 'Piloto'`, ocultar la columna Piloto. Botón "Nuevo robot" + modal; editar/borrar por fila con `ConfirmDeleteDialog`. Breadcrumbs `*.layout`.
- `resources/js/components/robots/robot-form-dialog.tsx` (`useForm`):
  - Campos: `nombre` (Input); `id_categoria` (Select requerido de `categorias`); `id_institucion` (Select opcional con opción "Sin institución" → valor vacío → enviar `null`); `id_piloto` (Select de `pilotos`, **solo si Admin**; oculto para Piloto).
  - Recibe por props los catálogos (`categorias`, `instituciones`, `pilotos`) desde la página.
  - Submit a `RobotController.store.url()` / `update.url(id_robot)` vía Wayfinder; `onError` → toast (igual que 2.1a).

### Navegación
- En `app-sidebar.tsx`, ítem "Robots" (`href: robots.index()`, icono `Bot` o `Cpu` de lucide) con `roles: ['Administrador', 'Piloto']`.

## Estrategia de pruebas (feature, PostgreSQL)

- **Autorización (`RobotCrudTest`):**
  - Juez y Coach reciben 403 en `GET /robots` y `POST /robots`.
  - Admin y Piloto acceden al index (200).
  - Piloto **no** puede `PUT`/`DELETE` un robot ajeno (403); **sí** el propio.
- **Scope:** un Piloto en index solo ve sus robots (assertInertia `has('robots', N)` con solo los suyos), no los de otro piloto.
- **Store:**
  - Admin crea robot asignando `id_piloto` de un usuario rol Piloto (assertDatabaseHas).
  - Un Piloto crea un robot enviando `id_piloto` de OTRO usuario, y el sistema lo fuerza a sí mismo (assert el robot quedó con `id_piloto` = el piloto autenticado).
- **Validación:** `id_categoria` ausente → error; `id_piloto` que no es rol Piloto (p.ej. un Juez) → error (en path admin); `id_institucion` nullable aceptado.
- Usar `actingAs` + factories; `User::factory()` con `['rol'=>'Piloto']`, `Robot::factory()`.

## Fuera de alcance (2.1b)
Inscripciones, inspección, competencia, brackets, tiempos. Búsqueda/paginación. Asignación de coach (no hay enlace en el esquema).

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (los previos + nuevos de robots).
2. Policy correcta: Juez/Coach 403; Piloto solo sus robots; Admin todos (verificado por tests).
3. Piloto se auto-asigna al crear (verificado por test).
4. UI lista + modales con selects de catálogo; piloto-select solo para Admin.
5. Nav muestra "Robots" a Administrador y Piloto.
6. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
