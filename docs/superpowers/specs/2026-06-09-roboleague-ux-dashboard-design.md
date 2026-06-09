# RoboLeague — Refinamiento UX: Dashboard por rol + primitivas de UI (Diseño)

**Fecha:** 2026-06-09
**Origen:** Petición del usuario — refinamiento de UI/UX (foco elegido: Dashboard + sistema de tablas). Este spec cubre el **sub-proyecto A: Dashboard por rol + primitivas**. El sub-proyecto B (sistema de tablas con búsqueda/orden/paginación en los 4 índices) va en su propio spec/plan después.
**Alcance:** Enriquecer y refinar visualmente el dashboard (ya por rol) con atajos y un panel de "qué atender", más unas primitivas de UI reutilizables. Excluye el sistema de tablas (sub-proyecto B) y cambios de auth/rutas.

## Contexto (verificado en código)

- `DashboardController::index` YA arma props por rol: `adminStats()` (robots inscritos, inscripciones pagadas/pendientes, total recaudado, inspecciones pendientes), `juezStats()` (inspecciones pendientes, encuentros por resolver), `robotOwnerStats($user)` (mis robots + tabla de robots con estado de pago). Devuelve `{ stats: [{label,value}], robots?: [...] }`.
- `resources/js/pages/dashboard.tsx` renderiza un `<StatCard label value>` por stat (grilla md:grid-cols-3) + una tabla simple de robots para Coach/Piloto.
- `StatCard` existe en `resources/js/components/stat-card.tsx` (label + value, plano).
- Tema Eléctrico (C0) ya aplicado (navy, acento azul, Chakra Petch en títulos). Iconos: `lucide-react` disponible. `AppLayout` provee el shell con sidebar.
- Auditoría visual (Playwright): el dashboard se ve plano — tarjetas sin icono ni jerarquía, acento azul casi sin usar, mucho espacio muerto, sin atajos ni "qué atender".

## Decisiones de diseño

- **Mantener** el modelo por rol existente; **enriquecer** sus props (atajos + items "qué atender") y **refinar** el render.
- Implementación visual guiada por la skill **frontend-design**; inspección real con Playwright (capturas antes/después).
- Sin nueva lógica de auth: los atajos se construyen en el controlador según el rol y se navegan con rutas nombradas/Wayfinder existentes.

## Primitivas de UI (reutilizables; base también de B)

- **`StatCard` (mejorado)**: props `label`, `value`, `icon?` (componente lucide), `tone?` ('default'|'accent'|'success'|'warning'|'danger'), `hint?`. Acento/tono via tokens del tema. Mantener compatibilidad con el uso actual (label+value).
- **`EmptyState`**: `icon`, `title`, `description?`, `action?` (label+href). Para listas/paneles vacíos.
- **`PageHeader`**: `title` (Chakra Petch), `description?`, `action?` (slot/CTA). Reutilizable en páginas internas.
- **`QuickActionCard`**: `icon`, `label`, `href` — tarjeta-atajo navegable.
- **`estadoBadgeVariant(estado: string)`** (helper TS en `@/lib`): mapea Pagado/Pendiente/Aprobado/Rechazado/Descalificado/etc. a variantes de color consistentes; usado por dashboard y (futuro) tablas.

## Backend (`DashboardController`)

Enriquecer cada variante (sin romper las props actuales; añadir campos):
- **Admin**: stats actuales + `accionesRapidas` (p. ej. Inscribir robot → inscripciones.create o index, Ver reportes/caja → reportes, Combate → combate.index) + `atencion` (inscripciones pendientes de pago, inspecciones pendientes — counts con enlace).
- **Juez**: stats actuales + `accionesRapidas` (Inspección, Combate, Tiempos) + `atencion` (inspecciones pendientes, encuentros por resolver).
- **Coach/Piloto**: stats + `robots` (ya existe) + `accionesRapidas` (Mis robots, Inscribir) + `atencion` (robots sin inscripción / inscripción pendiente de pago).
- Forma sugerida de los nuevos props (todos opcionales para no romper):
  - `accionesRapidas: [{ label: string, href: string, icon: string }]` (icon = nombre lucide; el front mapea).
  - `atencion: [{ label: string, value: int, href: string, tone: string }]`.
- Usar rutas nombradas existentes resueltas a URL en el controlador (o pasar el nombre y resolver en front con Wayfinder; decidir en el plan — preferir URLs resueltas para simplicidad).

## Frontend (`dashboard.tsx` + primitivas)

- `PageHeader` con saludo ("Hola, {nombre}") + rol como subtítulo/badge.
- Grilla de `StatCard` con icono y tono por stat (mapear cada stat conocido a su icono/tono; fallback neutro).
- Sección **Accesos rápidos**: grilla de `QuickActionCard` desde `accionesRapidas`.
- Panel **"Qué atender"**: lista desde `atencion` (cada item con su count, tono y enlace); si vacío, `EmptyState` ("Todo al día").
- Tabla de robots (Coach/Piloto): mantener, pero usar `estadoBadgeVariant` para el estado de pago y `EmptyState` cuando no hay robots.

## Estrategia de pruebas

- **Backend (feature, por rol)**: Admin recibe `accionesRapidas` y `atencion` con los counts correctos (p. ej. inscripciones pendientes); Juez recibe encuentros por resolver en `atencion`; Piloto recibe solo sus robots y atajos. Reusar/extender el test de dashboard existente si lo hay; si no, `DashboardTest` por rol con `assertInertia` sobre las props.
- **Primitivas**: no requieren test unitario de render (son presentacionales); se cubren por el build TS + verificación visual.
- **Visual (Playwright)**: capturas antes/después del dashboard por rol (Admin/Juez/Piloto), confirmando jerarquía, iconos, atajos y "qué atender".
- DoD técnico: `php artisan test` 100%, `npm run build` sin errores, Pint limpio.

## Fuera de alcance
Sistema de tablas (sub-proyecto B), formularios/modales, cambios de navegación del sidebar, nuevas rutas o lógica de autorización.

## Criterios de aceptación (DoD)
1. Primitivas `StatCard` (mejorado), `EmptyState`, `PageHeader`, `QuickActionCard`, helper `estadoBadgeVariant` creadas y usadas.
2. `DashboardController` expone `accionesRapidas` y `atencion` por rol (tests por rol).
3. Dashboard refinado: header, stat cards con icono/tono, accesos rápidos, panel "qué atender" (con EmptyState), tabla de robots con badges.
4. Verificación visual con Playwright (antes/después) por rol.
5. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
