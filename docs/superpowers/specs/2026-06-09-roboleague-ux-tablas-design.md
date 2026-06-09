# RoboLeague — Refinamiento UX: Sistema de tablas (DataTable) (Diseño)

**Fecha:** 2026-06-09
**Origen:** Refinamiento UI/UX, sub-proyecto B (tras el A: dashboard + primitivas, ya en `main`).
**Alcance:** Un componente `DataTable` reutilizable con búsqueda por texto (debounce), orden por columna, paginación server-side y filtros clave por lista; aplicado a los 4 índices (Inscripciones, Robots, Usuarios, Instituciones). Excluye export, selección múltiple, filtros por fecha y vistas guardadas.

## Contexto (verificado)

- Los índices hoy devuelven colecciones completas (`->get()->map()`), p. ej. `InscripcionController@index` mapea filas y respeta scope (no-admin solo ve sus inscripciones vía `whereHas('robot', id_piloto)`); `InstitucionController@index` hace `Institucion::orderBy('nombre')->get()`.
- Primitivas de A en `main`: `EmptyState`, `PageHeader`, `StatCard`, `QuickActionCard`, helper `estadoBadgeVariant` (en `@/lib/utils`). `Badge` en `@/components/ui/badge` (variants default/secondary/destructive/outline). shadcn `Select`/`Input`/`Button` disponibles.
- Tema Eléctrico aplicado. Inertia v3 (visitas parciales con `only`, `preserveState`). Wayfinder para rutas. Baseline tras A: 178 pruebas.

## Decisiones de diseño (confirmadas)

- **Paginación server-side** con `->paginate(15)->withQueryString()->through(fn ($m) => [...])`: la prop de cada índice pasa de array plano a paginador Inertia `{ data, links, meta }` (se actualiza cada página React acorde). `perPage` fijo = 15 (sin selector).
- **Búsqueda `?q=`** con **debounce ~300 ms** en el cliente → visita parcial `only` de la prop de la lista, `preserveState`+`preserveScroll`+`replace`.
- **Orden `?sort=&dir=`**: lista blanca de columnas ordenables por índice; columna no permitida → ignorada (orden por defecto). `dir` ∈ {asc, desc}.
- **Filtros clave por lista**: Inscripciones → estado_pago + categoría; Robots → categoría; Usuarios → rol; Instituciones → solo búsqueda.
- Implementación visual guiada por **frontend-design**; verificación con Playwright.
- Orden: DataTable base → Inscripciones (valida el patrón completo) → replicar en Robots/Usuarios/Instituciones (misma tanda).

## Backend (patrón común para los 4 índices)

Cada `index(Request $request)`:
1. Mantiene `authorize` y scope existentes.
2. Construye `$query` (con `with(...)` de relaciones para el mapeo).
3. **Búsqueda**: si `$request->filled('q')`, aplica `where`/`whereHas` sobre columnas seguras de esa lista.
4. **Filtros**: lee params específicos (`estado`, `categoria`, `rol`) y aplica `where` si vienen.
5. **Orden**: `$sort = $request->query('sort')`; si está en la lista blanca de la lista, `orderBy($sort, $dir)`; si no, orden por defecto. `$dir = $request->query('dir') === 'asc' ? 'asc' : 'desc'`.
6. `->paginate(15)->withQueryString()->through(fn ($m) => [... mismo shape de fila que hoy ...])`.
7. Pasa también los datos de filtros (p. ej. lista de categorías para el dropdown) y los valores actuales de `q/sort/dir/filtros` para que el front refleje el estado.

Listas blancas de orden (ejemplos): Inscripciones → `id_inscripcion`, `estado_pago`, `monto_pagado`; Robots → `nombre`; Usuarios → `name`, `email`, `rol`; Instituciones → `nombre`. (Búsqueda por relación NO se ordena por relación; solo columnas propias.)

## Frontend

### `resources/js/components/data-table/` 
- **`data-table.tsx`**: props `columns` (`{ key, header, sortable?, render? }[]`), `rows` (`data`), `meta`, `links`, `toolbar?` (ReactNode), `routeUrl` (string base para las visitas), `emptyState` (props para `EmptyState`). Renderiza cabeceras (con ▲▼ en sortables), filas con hover/densidad consistente, paginación (desde `links`), y `EmptyState` si `rows` vacío.
- **`use-table-query.ts`**: hook que centraliza el merge de query params (`set(key, value)` preserva los demás), con debounce para `q`. Hace `router.get(routeUrl, params, { preserveState: true, preserveScroll: true, replace: true, only: [<prop>] })`.
- **`data-table-toolbar` / patrón de toolbar**: input de búsqueda (debounce) + `Select` por filtro. Cada página compone su toolbar.

### Páginas (`instituciones`, `usuarios`, `robots`, `inscripciones`)
- Adoptan `DataTable` con sus `columns` y toolbar. Reutilizan `PageHeader` + `estadoBadgeVariant`. La acción principal (p. ej. "Inscribir robot") va en `PageHeader.action`.
- Cada página define su lista blanca de columnas ordenables coherente con el backend.

## Estrategia de pruebas (feature, por controlador)

- **Búsqueda**: `?q=<texto>` reduce el conjunto (incluye búsqueda por relación donde aplique, p. ej. Inscripciones por nombre de robot).
- **Orden**: `?sort=<col permitida>&dir=asc` ordena; `?sort=<col no permitida>` → se ignora (sin error, orden por defecto).
- **Paginación**: estructura paginada (`data`/`meta`); `?page=2` devuelve la segunda página; `meta.per_page === 15`.
- **Filtros**: `?estado=Pendiente` (Inscripciones), `?categoria=<id>` (Robots/Inscripciones), `?rol=Juez` (Usuarios) acotan.
- **Scope/autorización**: no-admin sigue viendo solo lo suyo en Inscripciones/Robots; roles sin acceso → 403 (como hoy).
- Visual (Playwright): Inscripciones con búsqueda/orden/paginación; estado vacío.
- DoD técnico: `php artisan test` 100%; `npm run build` sin errores; Pint limpio.

## Fuera de alcance
Export CSV, selección múltiple / acciones masivas, filtros por rango de fechas, guardar vistas, selector de tamaño de página.

## Criterios de aceptación (DoD)
1. `DataTable` + `useTableQuery` reutilizables, con búsqueda (debounce), orden (lista blanca), paginación y toolbar de filtros.
2. Los 4 índices paginan server-side (`{data,meta,links}`) conservando scope/autorización; búsqueda/orden/filtros por query param con `withQueryString`.
3. Columna de orden no permitida se ignora sin romper; per_page = 15.
4. Páginas usan PageHeader + EmptyState + badges; verificación visual Playwright en Inscripciones.
5. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
