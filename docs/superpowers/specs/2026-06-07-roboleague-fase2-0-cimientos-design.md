# RoboLeague — Fase 2.0: Roles, Autorización y Dashboard por rol (Diseño)

**Fecha:** 2026-06-07
**Fase padre:** Fase 2 (Backend + Frontend), descompuesta en sub-proyectos 2.0–2.5.
**Alcance de esta spec:** Únicamente el sub-proyecto **2.0 Cimientos**. Los CRUDs de módulos (2.1 Participantes, 2.2 Inscripciones/Financiero, 2.3 Inspección, 2.4 Competencia, 2.5 Reportes) se especifican por separado.

## Contexto

La Fase 1 (capa de datos PostgreSQL) está completa y fusionada a `main`. El repo es un starter kit Laravel 13 + Fortify + Inertia v3/React + Tailwind v4 con layouts de sidebar/header y páginas de settings. La tabla `users` ya tiene la columna `rol` (CHECK enum: Administrador, Juez, Coach, Piloto) con default `Piloto`. **No existe** ninguna capa de autorización por rol todavía; tampoco controladores de dominio.

Este sub-proyecto establece las primitivas de autorización y un dashboard que cambia según el rol, sobre las que se construirán los módulos posteriores.

## Decisiones de diseño

### Autorización nativa (sin dependencias nuevas)
Roles fijos (4 valores), así que no se usa `spatie/laravel-permission`. Se implementa con primitivas nativas de Laravel. CLAUDE.md prohíbe añadir dependencias sin aprobación.

### Gates diferidos a los módulos
Los cimientos entregan el **mecanismo de rol** (enum + helpers + middleware), no Gates por capacidad. Cada módulo posterior define sus propios Gates/policies cuando se construya (evita Gates especulativos sobre features inexistentes).

## Componentes

### 1. `App\Enums\RolUsuario` (enum PHP string-backed)
Casos exactamente: `Administrador`, `Juez`, `Coach`, `Piloto`, con valores string idénticos (coinciden con el CHECK de `users.rol`). Se usa para el cast del modelo y para tipar los helpers/middleware.

### 2. Modelo `User` extendido
- Castear `rol` a `RolUsuario::class` en `casts()`.
- Helpers:
  - `hasRole(RolUsuario ...$roles): bool` — true si el rol del usuario está entre los dados.
  - `isAdministrador(): bool`, `isJuez(): bool`, `isCoach(): bool`, `isPiloto(): bool` — atajos sobre `hasRole`.
- `rol` debe quedar incluido en lo que se serializa al frontend (no ocultarlo).

### 3. Middleware `EnsureUserHasRole` (alias `role`)
- Registrado como alias de ruta `role` en `bootstrap/app.php`.
- Uso: `->middleware('role:Juez,Administrador')`.
- Lógica: si no hay usuario autenticado o su `rol` no está en la lista permitida, aborta con 403. Acepta múltiples roles separados por coma.

### 4. Compartir el rol al frontend
- `HandleInertiaRequests::share()` ya comparte `auth.user`; garantizar que `rol` (string del enum) viaje en el payload para que React pueda adaptar la vista. Tipar `rol` en la interfaz TypeScript `User` del frontend.

### 5. Dashboard por rol
- Nueva ruta: la actual `Route::inertia('dashboard', 'dashboard')` se reemplaza por `Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')` dentro del grupo `auth, verified`.
- **`App\Http\Controllers\DashboardController@index`**: arma props según `$request->user()->rol` y hace `Inertia::render('dashboard', [...])`.
  - **Administrador**: `robotsInscritos` (count robots con inscripción), `inscripcionesPagadas`, `inscripcionesPendientes`, `totalRecaudado` (SUM monto_pagado de inscripciones Pagado), `inspeccionesPendientes` (count inspecciones estado Pendiente).
  - **Juez**: `inspeccionesPendientes`, `encuentrosPorResolver` (encuentros sin ganador definido en participantes).
  - **Coach**: `misRobots` (robots cuyo piloto es el usuario), cada uno con su `estado_pago` más reciente.
  - **Piloto**: `misRobots` con sus resultados/mejores tiempos (desde `vista_posiciones` cuando aplique).
- **`dashboard.tsx`**: renderiza un conjunto de tarjetas distinto según `auth.user.rol`, usando un componente reutilizable `StatCard` (título, valor, opcional icono). Para Coach/Piloto, una lista simple de robots.

> Nota: las consultas usan tablas existentes (robots, inscripciones, inspecciones_checklist, vista_posiciones). Coach/Piloto filtran por `id_piloto = auth()->id()`.

## Estructura de archivos

- Create: `app/Enums/RolUsuario.php`
- Modify: `app/Models/User.php` (cast + helpers)
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php` (registrar alias `role`)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (asegurar `rol` en payload) — solo si no viaja ya
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php` (ruta dashboard → controlador)
- Modify: `resources/js/pages/dashboard.tsx` (tarjetas por rol)
- Create: `resources/js/components/stat-card.tsx`
- Modify: tipos TS del usuario (donde se declare la interfaz `User`, p. ej. `resources/js/types/index.d.ts`) para incluir `rol`
- Tests: `tests/Unit/RolUsuarioTest.php`, `tests/Feature/RoleMiddlewareTest.php`, `tests/Feature/DashboardTest.php`

## Estrategia de pruebas

- **Unit `RolUsuarioTest`**: el enum tiene los 4 casos con los valores correctos; `hasRole`/`isJuez`/etc. de `User` devuelven lo esperado (usando `User::factory()->juez()` etc.).
- **Feature `RoleMiddlewareTest`**: una ruta protegida con `role:Administrador` devuelve 403 a un Juez/Coach/Piloto y 200 a un Administrador; usuario no autenticado es redirigido/bloqueado.
- **Feature `DashboardTest`**: cada rol recibe las props correctas (un Administrador ve `totalRecaudado`/`inspeccionesPendientes`; un Coach ve `misRobots` filtrados a sus robots y no los de otros; un Juez ve `inspeccionesPendientes`). Usar `assertInertia` para verificar las props.

## Fuera de alcance (2.0)
CRUDs de módulos, navegación lateral con secciones futuras, Gates/policies por capacidad, edición de roles desde UI, API REST. Todo eso es 2.1+.

## Criterios de aceptación (DoD)
1. `php artisan test` pasa al 100% (incluye los nuevos tests y los 60 existentes).
2. El middleware `role` bloquea (403) y permite según rol, verificado por tests.
3. El dashboard renderiza props/tarjetas distintas por rol, verificado por tests con `assertInertia`.
4. `rol` está disponible y tipado en el frontend.
5. `vendor/bin/pint --dirty` sin cambios pendientes; `npm run build` (o lint TS) sin errores.
