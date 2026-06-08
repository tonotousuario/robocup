# RoboLeague — Fase 2.1a: CRUD de Instituciones y Usuarios (Diseño)

**Fecha:** 2026-06-07
**Fase padre:** Fase 2 (Backend + Frontend) → sub-proyecto 2.1 Participantes, dividido en **2.1a (Instituciones + Usuarios)** y 2.1b (Robots, posterior).
**Alcance de esta spec:** Únicamente 2.1a. Robots y su gestión son 2.1b.

## Contexto

Fase 1 (datos) y Fase 2.0 (roles + autorización + dashboard por rol) están en `main`. Existe:
- Enum `App\Enums\RolUsuario` (Administrador/Juez/Coach/Piloto), cast en `User`, helpers (`isAdministrador`, etc.).
- Middleware `role` (alias) que aborta 403 según rol.
- Modelos Eloquent de Fase 1: `Institucion` (tabla `instituciones`, PK `id_institucion`, campos `nombre`/`tipo`/`estado`), `User` extendido (`name`, `apellidos`, `email`, `telefono`, `rol`), `Robot` (FK `id_piloto`→users No Action), `InspeccionChecklist` (FK `id_juez`→users No Action).
- Kit UI shadcn en `resources/js/components/ui/` (incluye `dialog`, `input`, `select`, `label`, `button`, `badge`, `dropdown-menu`). No hay componente `Table`; se usa `<table>` con clases Tailwind (patrón ya usado en `dashboard.tsx`).
- Sidebar con array estático `mainNavItems` en `resources/js/components/app-sidebar.tsx`; tipo `NavItem` en `resources/js/types/navigation.ts`.
- Wayfinder genera acciones TS por controlador en `resources/js/actions/...`.
- Formularios usan `useForm` de Inertia (ver `resources/js/pages/settings/profile.tsx`).

## Decisiones de diseño

- **Solo Administrador** gestiona instituciones y usuarios. Rutas en grupo `auth, verified, role:Administrador`. Sin Gates nuevos (el middleware basta; YAGNI).
- **UI lista + modales**: una página índice con tabla; crear/editar en `Dialog`; borrar con confirmación en `Dialog`.
- **Password de usuario**: el admin define una inicial al crear; al editar el campo va vacío = no cambia.
- **Borrado seguro de usuarios**: bloquear si el usuario está referenciado (piloto de robots o juez de inspecciones) y prohibir auto-borrado; responder con error de validación, no excepción.
- Paginación/búsqueda **fuera de alcance** (se añadirá si el volumen lo exige).

## Backend

### Rutas (`routes/web.php`)
Dentro de un nuevo grupo:
```php
Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'store', 'update', 'destroy']);
});
```

### `InstitucionController`
- `index`: `Inertia::render('instituciones/index', ['instituciones' => Institucion::orderBy('nombre')->get()])`.
- `store(StoreInstitucionRequest)`: crea, redirect back con flash de éxito.
- `update(UpdateInstitucionRequest, Institucion)`: actualiza (route model binding por `id_institucion`).
- `destroy(Institucion)`: elimina (robots quedan con `id_institucion` NULL por el FK Set Null).

### `UsuarioController`
- `index`: `Inertia::render('usuarios/index', ['usuarios' => User::orderBy('name')->get(['id','name','apellidos','email','telefono','rol'])])`.
- `store(StoreUsuarioRequest)`: crea con `password` hasheada y `email_verified_at` = now() (el admin lo crea ya verificado).
- `update(UpdateUsuarioRequest, User)`: actualiza; si `password` viene no vacío lo re-hashea, si no, lo deja.
- `destroy(User)`: valida antes de borrar:
  - Si `$usuario->id === auth()->id()` → error "No puedes eliminar tu propia cuenta".
  - Si tiene robots como piloto (`robotsComoPiloto()->exists()`) o inspecciones como juez (`inspecciones()->exists()`) → error "No se puede eliminar: el usuario tiene robots o inspecciones asociadas".
  - Si no, elimina.
  - Los errores se devuelven como `back()->withErrors([...])` para mostrarse en la UI.

### Form Requests (`app/Http/Requests/`)
- `StoreInstitucionRequest` / `UpdateInstitucionRequest`: `authorize()` → true (la ruta ya exige rol). Reglas: `nombre` required|string|max:255; `tipo` required|in:Pública,Privada,Independiente; `estado` required|string|max:255.
- `StoreUsuarioRequest`: `name`, `apellidos` required|string|max:255; `email` required|email|unique:users,email; `telefono` nullable|string|max:255; `rol` required|enum:RolUsuario (regla `Rule::enum(RolUsuario::class)`); `password` required|string|min:8.
- `UpdateUsuarioRequest`: igual pero `email` único ignorando el id actual (`Rule::unique('users','email')->ignore($this->route('usuario'))`); `password` nullable|string|min:8.

## Frontend

### Tipos
- En `resources/js/types/` añadir tipos `Institucion` (`id_institucion`, `nombre`, `tipo`, `estado`) y un tipo de fila de usuario `UsuarioRow` (`id`, `name`, `apellidos`, `email`, `telefono`, `rol`).

### Páginas
- `resources/js/pages/instituciones/index.tsx`: tabla (Nombre, Tipo, Estado, acciones), botón "Nueva institución", modal de alta/edición, confirmación de borrado. Bloque `*.layout` con breadcrumbs (convención del repo).
- `resources/js/pages/usuarios/index.tsx`: tabla (Nombre, Apellidos, Correo, Teléfono, Rol con `Badge`, acciones), botón "Nuevo usuario", modal de alta/edición (incluye `Select` de rol y campo password), confirmación de borrado.

### Componentes
- Un componente modal de formulario por entidad (`institucion-form-dialog.tsx`, `usuario-form-dialog.tsx`) con `useForm`, que sirve para crear (sin registro) y editar (con registro precargado). Reutilizan `Dialog`, `Input`, `Label`, `Select`, `Button`, `InputError`.
- Borrado: `Dialog` de confirmación reutilizable o inline por página.
- Las llamadas al backend usan las acciones generadas por Wayfinder (`@/actions/...`) en vez de URLs hardcodeadas.

### Navegación por rol
- Extender `NavItem` (en `resources/js/types/navigation.ts`) con `roles?: Array<User['rol']>`.
- En `app-sidebar.tsx`, definir los ítems "Instituciones" y "Usuarios" con `roles: ['Administrador']` y filtrar `mainNavItems` según `auth.user.rol` (vía `usePage`). Los ítems sin `roles` se muestran a todos (p. ej. Dashboard).

## Estrategia de pruebas (feature, PostgreSQL)

- **Autorización (`ParticipantesAuthTest` o por recurso):** un usuario Juez/Coach/Piloto recibe 403 en `GET /instituciones`, `POST /instituciones`, y equivalentes de usuarios; un Administrador recibe 200/redirect según corresponda.
- **Instituciones (`InstitucionCrudTest`):** admin crea (assertDatabaseHas), edita, borra (assertDatabaseMissing); `tipo` inválido → error de validación (422/redirect con errores); borrar una institución con robots deja `id_institucion` NULL en el robot.
- **Usuarios (`UsuarioCrudTest`):** admin crea usuario con password (assert `Hash::check`), `email` duplicado rechazado, `rol` inválido rechazado; editar sin password no cambia el hash; **no** puede borrar un usuario que es piloto de un robot (error, usuario sigue en BD); **no** puede borrarse a sí mismo.
- Usar `assertInertia` para verificar props de los index y `actingAs` con `User::factory()` + estados de rol.

## Fuera de alcance (2.1a)
Robots (2.1b), paginación/búsqueda/orden avanzado, exportaciones, edición de password del propio perfil (ya en settings), gestión de avatares.

## Criterios de aceptación (DoD)
1. `php artisan test` pasa al 100% (incluye los 67 previos + los nuevos).
2. Solo Administrador accede a los recursos; otros roles 403 (verificado por tests).
3. CRUD de instituciones y usuarios funcional vía modales; validaciones y borrado seguro verificados por tests.
4. Nav muestra "Instituciones"/"Usuarios" solo al Administrador.
5. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
