# RoboLeague — C0: Fundación visual / Tema "Eléctrico" (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Refinado de UI (opción C, rediseño completo), dividido en **C0 (tema/identidad)**, C1 (pulido app), C2 (dashboard+bracket), C3 (landing+proyección).
**Alcance de esta spec:** Solo C0 — tokens de color, modo oscuro por defecto, fuente display y marca "RoboLeague". Base que C1–C3 reutilizan.

## Contexto

App Laravel 13 + Inertia/React + Tailwind v4 con tema shadcn (tokens OKLCH en `resources/css/app.css`: `@theme` + `:root` claro + `.dark` oscuro), hoy en **escala de grises**. Las fuentes se cargan con la directiva nativa `@fonts` (`Illuminate\Foundation\ViteFonts`) en `resources/views/app.blade.php`, que autodetecta los tokens `--font-*` del tema y los sirve desde Bunny Fonts. El logo (`resources/js/components/app-logo.tsx`) dice "Laravel Starter Kit". El modo (claro/oscuro/system) se gestiona en `resources/js/hooks/use-appearance.ts` + `initializeTheme()` en `app.tsx`; hay un toggle en Ajustes (`settings/appearance`).

Decisiones del brainstorming (companion visual): paleta **Eléctrico** (azul/cian sobre navy), **oscuro por defecto** con toggle claro, fuente display **Chakra Petch** para marca/encabezados (cuerpo sigue en Instrument Sans).

## Decisiones de diseño

- Recolorar los tokens shadcn al acento azul Eléctrico (claro y oscuro); oscuro = navy real.
- Modo **oscuro por defecto** (sin quitar el toggle claro).
- Añadir token `--font-display: 'Chakra Petch'` (auto-cargado por `@fonts`); aplicarlo a la marca y a los títulos de página (`h1`).
- Marca "RoboLeague" (wordmark + glifo) y `APP_NAME=RoboLeague`.
- Sin tocar lógica de negocio; cambios CSS/marca/config.

## Componentes

### 1. Tokens de color (`resources/css/app.css`)
Reemplazar los valores grises por el acento Eléctrico. Valores OKLCH guía (equivalentes a los hex indicados):
- Acento azul `#3b82f6` ≈ `oklch(0.62 0.19 260)`; azul claro (dark primary) `#60a5fa` ≈ `oklch(0.71 0.15 257)`; cian `#22d3ee` ≈ `oklch(0.79 0.14 214)`.
- **`:root` (claro)**: `--primary` = azul; `--primary-foreground` = blanco (`oklch(0.985 0 0)`); `--ring` = azul; `--sidebar-primary` = azul; `--sidebar-primary-foreground` = blanco; `--sidebar-ring` = azul. Fondo/cards quedan claros.
- **`.dark` (oscuro)**: navy real —
  - `--background`/`--sidebar` ≈ `oklch(0.17 0.025 265)` (≈`#0a0e17`).
  - `--card`/`--popover` ≈ `oklch(0.21 0.03 264)` (≈`#0f1626`).
  - `--border`/`--input` ≈ `oklch(0.30 0.04 262)` (azulado).
  - `--primary` = azul claro `#60a5fa`; `--primary-foreground` = navy oscuro; `--ring` = cian; `--sidebar-primary` = azul claro; `--sidebar-accent` un navy un punto más claro.
  - `--muted-foreground` con leve tinte frío para legibilidad.
- Mantener `--destructive` rojo y `--radius` actuales.

### 2. Fuente display (`resources/css/app.css`)
- En `@theme`, añadir: `--font-display: 'Chakra Petch', ui-sans-serif, system-ui, sans-serif;` → `@fonts` la sirve automáticamente desde Bunny.
- Aplicar a títulos de página y marca. Para no editar cada página, añadir una regla base:
  ```css
  @layer base {
      h1 { font-family: var(--font-display); }
  }
  ```
  (Los `<h1 className="text-xl font-semibold">` de cada índice y el encabezado de login heredan la fuente. Los `DialogTitle`/`h2`/`h3` siguen en sans para no recargar.)

### 3. Modo oscuro por defecto (`resources/js/hooks/use-appearance.ts`)
- Cambiar el valor por defecto de la apariencia de `'system'` a `'dark'` (cuando no hay preferencia guardada). El toggle en Ajustes (`claro/oscuro/system`) sigue funcionando y persistiendo.

### 4. Marca "RoboLeague"
- `resources/js/components/app-logo.tsx`: cambiar el texto a **"RoboLeague"** con clase `font-display`; el cuadro del ícono usa el acento (`bg-primary`/gradiente azul→cian).
- `resources/js/components/app-logo-icon.tsx`: reemplazar el glifo del starter por un ícono de robótica (usar `Bot` o `Cpu` de lucide, o un SVG simple). Mantener la firma/props (`className`) para no romper usos.
- `APP_NAME=RoboLeague` en `.env` y `.env.example` (alimenta `<title>` "… - RoboLeague" vía `VITE_APP_NAME` y el header de auth).

## Estrategia de pruebas / verificación

C0 es visual/estilístico; no añade lógica testeable. Verificación:
- `php artisan test --compact` → las 139 pruebas siguen verdes (cambios de CSS/marca/config no afectan el backend).
- `npm run build` → sin errores (TS/Vite); confirma que la fuente y tokens compilan.
- `vendor/bin/pint --dirty` limpio (si se tocó PHP).
- **Verificación visual manual**: login, sidebar (marca "RoboLeague", acento azul), dashboard, y una tabla — en oscuro (default) y alternando a claro con el toggle de Ajustes.

## Fuera de alcance (C0)
Toasts/estados vacíos/badges/skeletons (C1), dashboard y bracket (C2), landing público y proyección (C3). No se cambian componentes de UI individuales más allá de la marca.

## Criterios de aceptación (DoD)
1. Tokens Eléctrico aplicados en claro y oscuro; oscuro por defecto con toggle claro funcional.
2. `--font-display: Chakra Petch` cargada por `@fonts` y aplicada a marca + `h1`.
3. Marca "RoboLeague" en sidebar/logo y `APP_NAME=RoboLeague` (títulos).
4. `php artisan test` 139/139; `npm run build` sin errores; Pint limpio.
5. Verificado visualmente en oscuro y claro.
