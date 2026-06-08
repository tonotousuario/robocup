# RoboLeague — Modo Proyección (pantalla de competición) (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Refinado de UI (opción C). Es la pieza de "pantalla de proyección" de C3, adelantada por petición del usuario.
**Dependencia:** Construye sobre **C0** (tema Eléctrico + Chakra Petch). C0 debe estar en `main` antes de implementar esto.
**Alcance:** Una vista pública de proyección para cañón/pantallón, con bracket de combate en vivo y vistas seleccionables. Landing público del torneo y otras pantallas quedan fuera.

## Contexto

En `main` (tras fusionar C0): Fases 1–2.5 completas + tema Eléctrico. El bracket interactivo vive en `combate/index.tsx` (controlador `EncuentroController`, con `puedeRegistrar`/acciones). Las posiciones de tiempo se calculan en `vista_posiciones` (BD). Layouts en `resources/js/app.tsx` se asignan por nombre de página (`auth/`, `settings/`, default `AppLayout` con sidebar). Inertia v3 soporta polling (`router.reload`).

Modelos: `Encuentro` (rel `categoria()`, `participantes()`), `ParticipanteEncuentro` (rel `inscripcion()`), `Categoria` (`tipo_evaluacion`).

## Decisiones de diseño

- **Acceso público sin login**: rutas fuera del grupo `auth`, solo lectura; exponen nombres de robots/rondas/categoría (nada sensible).
- **Layout `projection`** full-screen, sin sidebar/navegación, oscuro navy fijo, alto contraste, tipografía display grande (leerse de lejos).
- **3 vistas seleccionables** por el operador (barra de control + query param `?vista=`): `bracket`, `marcador`, `rotar`.
- **Auto-refresh por polling** cada **5 s** (sin WebSockets; autonomía local).
- Estado por **query params** (`vista`, `categoria`) → compartible por URL y estable ante el reload.

## Backend

### `ProyeccionController` (sin policy; rutas públicas)
- **`index()`**: `Inertia::render('proyeccion/index', ['categoriasCombate' => ..., 'categoriasTiempo' => ...])` — selector inicial (categorías de Combate y de Tiempo, `{id_categoria, nombre}`).
- **`show(Categoria $categoria)`**: datos para la pantalla de una categoría de Combate:
  - `encuentros`: los de la categoría con `participantes.inscripcion.robot`, mapeados a `{ id_encuentro, ronda, id_encuentro_siguiente, participantes:[{id_inscripcion, robot, es_ganador}] }` (igual forma que `EncuentroController@index`).
  - `enVivo`: el encuentro con exactamente 2 participantes y sin ganador, de la ronda más avanzada disponible (o null) → `{ id_encuentro, ronda, robots:[a,b] }`.
  - `posiciones`: para la vista "rotar", las posiciones de la **primera categoría de Tiempo** (o de un `?tiempos=` opcional) desde `vista_posiciones`, ordenadas: `{ robot, categoria, mejor_tiempo }`.
  - `categoria`: `{ id_categoria, nombre }`.
- Route-model binding por `id_categoria` (Categoria ya define `getRouteKeyName`? Si no, usar `{categoria}` y resolver por `id_categoria` — confirmar; Categoria PK es `id_categoria`, así que el binding por defecto usa esa columna).

### Rutas (`routes/web.php`, FUERA de cualquier grupo `auth`)
```php
Route::get('proyeccion', [ProyeccionController::class, 'index'])->name('proyeccion.index');
Route::get('proyeccion/combate/{categoria}', [ProyeccionController::class, 'show'])->name('proyeccion.combate');
```

## Frontend

### Layout `projection` (`resources/js/layouts/projection-layout.tsx`)
- Full-screen, fondo navy fijo (clase `dark` forzada en el contenedor), sin sidebar. Header compacto: logo RoboLeague + nombre de categoría. Resto = área de proyección.
- Registrar en `app.tsx`: las páginas `proyeccion/` usan este layout (no `AppLayout`).

### Página selector (`resources/js/pages/proyeccion/index.tsx`)
- Lista categorías de Combate (enlaces a `/proyeccion/combate/{id}`) y nota de uso. Pantalla simple para elegir qué proyectar.

### Página de proyección (`resources/js/pages/proyeccion/combate/show.tsx` o `proyeccion/combate-show.tsx`)
- Lee `vista` y `categoria` de query params (default `vista=bracket`).
- **Barra de control** (arriba, semi-transparente, se oculta tras unos segundos de inactividad o con tecla/botón): botones Bracket · Marcador · Rotar; y, si aplica, selector de categoría. Cambiar vista actualiza el query param vía `router.get` con `preserveState`.
- **Vista `bracket`**: el árbol por columnas de ronda (orden `Dieciseisavos…Final`), tipografía XL, ganadores con check + acento, campeón destacado.
- **Vista `marcador`**: franja superior grande con `enVivo` (Robot A vs Robot B, ronda) + el bracket debajo.
- **Vista `rotar`**: alterna cada N s (p. ej. 12 s, vía `setInterval` en el cliente) entre el bracket y una tabla de posiciones (`posiciones`) a pantalla grande.
- **Auto-refresh**: `useEffect` con `setInterval(5000)` → `router.reload({ only: ['encuentros','enVivo','posiciones'] })`. Limpiar el intervalo al desmontar.
- Componentes reutilizables: `projection-bracket.tsx` (render del árbol XL) y `projection-standings.tsx` (tabla posiciones XL).

### Tipos (`resources/js/types/models.ts`)
- `ProyeccionEnVivo`: `{ id_encuentro:number; ronda:string; robots:(string|null)[] }`.
- (Reutiliza `EncuentroBracket` para los encuentros y `PosicionReporte`/un tipo equivalente para posiciones.)

## Estrategia de pruebas (feature, PostgreSQL)

- **Acceso público**: `GET /proyeccion` y `GET /proyeccion/combate/{categoria}` responden **200 sin autenticar** (un invitado, sin `actingAs`).
- **`show` datos**: con un bracket generado, devuelve los encuentros de la categoría; `enVivo` apunta a un encuentro con 2 participantes y sin ganador; tras marcar todos los ganadores de una ronda, `enVivo` avanza o es null.
- **Posiciones**: con intentos sembrados en una categoría de Tiempo, `posiciones` trae el mejor tiempo ordenado.
- **404**: categoría inexistente → 404.
- (La selección de vista y el polling son de cliente; se verifican visualmente.)

## Fuera de alcance
Landing público del torneo, WebSockets/tiempo real, edición desde la proyección, animaciones complejas, autenticación/token (es público por enlace).

## Criterios de aceptación (DoD)
1. Rutas `/proyeccion` públicas (200 sin login); categoría inexistente 404 (tests).
2. `show` devuelve encuentros + `enVivo` correcto + posiciones (tests).
3. Layout `projection` full-screen sin sidebar, oscuro, alto contraste, tipografía display grande.
4. 3 vistas seleccionables (bracket/marcador/rotar) por barra de control + query param; auto-refresh 5 s.
5. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
6. Verificado visualmente en pantalla grande/proyección.
