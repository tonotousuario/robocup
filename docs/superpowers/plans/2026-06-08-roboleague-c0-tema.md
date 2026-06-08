# RoboLeague C0 — Tema "Eléctrico" · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicar la identidad visual "Eléctrico" (azul/cian sobre navy), oscuro por defecto con toggle claro, fuente display Chakra Petch y la marca RoboLeague.

**Architecture:** Recolorear los tokens shadcn (OKLCH) en `resources/css/app.css` (claro y oscuro), añadir el token `--font-display` (auto-cargado por la directiva `@fonts`), cambiar el default de apariencia a oscuro en el hook, y reemplazar el logo/nombre por RoboLeague. Cambios puramente de estilo/marca/config; sin lógica de negocio.

**Tech Stack:** Tailwind v4 (tema shadcn OKLCH), Inertia/React, Laravel ViteFonts (`@fonts`), lucide-react.

**Convenciones/contexto:**
- `resources/css/app.css`: `@theme` (tokens + fuentes), `:root` (claro), `.dark` (oscuro), `@layer base` al final.
- `@fonts` (`Illuminate\Foundation\ViteFonts`) autodetecta tokens `--font-*` y sirve la fuente desde Bunny — basta declarar `--font-display`.
- Apariencia: `resources/js/hooks/use-appearance.tsx` (default `'system'`; el toggle vive en `settings/appearance`).
- Marca: `resources/js/components/app-logo.tsx` ("Laravel Starter Kit") + `app-logo-icon.tsx` (SVG del starter).
- **Esta fase no tiene pruebas nuevas** (es visual). Verificación = `php artisan test` sigue 139/139 + `npm run build` OK + Pint limpio + revisión visual.
- Tras tocar PHP: `vendor/bin/pint --dirty --format agent`.

---

## File Structure

- Modify: `resources/css/app.css` — tokens Eléctrico (`:root` + `.dark`), `--font-display`, regla base `h1`.
- Modify: `resources/js/hooks/use-appearance.tsx` — default oscuro.
- Modify: `resources/js/components/app-logo.tsx` — wordmark RoboLeague + acento + `font-display`.
- Modify: `resources/js/components/app-logo-icon.tsx` — glifo robótico (lucide `Bot`).
- Modify: `.env`, `.env.example` — `APP_NAME=RoboLeague`.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-c0-tema
```
Expected: `Switched to a new branch 'feature/roboleague-c0-tema'`.

---

## Task 1: Tokens de color, fuente display y regla de títulos

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Añadir el token de fuente display**

En `resources/css/app.css`, dentro del bloque `@theme { ... }`, justo después del bloque `--font-sans: ...;` (que termina en `'Noto Color Emoji';`), añadir:
```css
    --font-display:
        'Chakra Petch', ui-sans-serif, system-ui, sans-serif;
```

- [ ] **Step 2: Recolorear el acento en `:root` (modo claro)**

En el bloque `:root { ... }`, reemplazar estas 6 líneas (acento gris → azul Eléctrico); el resto de `:root` queda igual:
```css
    --primary: oklch(0.62 0.19 260);
    --primary-foreground: oklch(0.985 0 0);
    --ring: oklch(0.62 0.19 260);
    --sidebar-primary: oklch(0.62 0.19 260);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-ring: oklch(0.62 0.19 260);
```
(Es decir: cambiar los valores actuales de `--primary`, `--primary-foreground`, `--ring`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-ring` por los de arriba. No tocar las demás variables de `:root`.)

- [ ] **Step 3: Reemplazar el bloque `.dark` completo por la versión navy**

Reemplazar TODO el bloque `.dark { ... }` por:
```css
.dark {
    --background: oklch(0.17 0.025 265);
    --foreground: oklch(0.97 0.01 250);
    --card: oklch(0.21 0.03 264);
    --card-foreground: oklch(0.97 0.01 250);
    --popover: oklch(0.21 0.03 264);
    --popover-foreground: oklch(0.97 0.01 250);
    --primary: oklch(0.71 0.15 257);
    --primary-foreground: oklch(0.17 0.025 265);
    --secondary: oklch(0.27 0.03 264);
    --secondary-foreground: oklch(0.97 0.01 250);
    --muted: oklch(0.27 0.03 264);
    --muted-foreground: oklch(0.70 0.03 255);
    --accent: oklch(0.27 0.03 264);
    --accent-foreground: oklch(0.97 0.01 250);
    --destructive: oklch(0.40 0.14 25.7);
    --destructive-foreground: oklch(0.64 0.24 25.3);
    --border: oklch(0.30 0.04 262);
    --input: oklch(0.30 0.04 262);
    --ring: oklch(0.79 0.14 214);
    --chart-1: oklch(0.488 0.243 264.376);
    --chart-2: oklch(0.696 0.17 162.48);
    --chart-3: oklch(0.769 0.188 70.08);
    --chart-4: oklch(0.627 0.265 303.9);
    --chart-5: oklch(0.645 0.246 16.439);
    --sidebar: oklch(0.20 0.028 264);
    --sidebar-foreground: oklch(0.97 0.01 250);
    --sidebar-primary: oklch(0.71 0.15 257);
    --sidebar-primary-foreground: oklch(0.17 0.025 265);
    --sidebar-accent: oklch(0.27 0.03 264);
    --sidebar-accent-foreground: oklch(0.97 0.01 250);
    --sidebar-border: oklch(0.30 0.04 262);
    --sidebar-ring: oklch(0.79 0.14 214);
}
```

- [ ] **Step 4: Aplicar la fuente display a los títulos de página (`h1`)**

En el bloque `@layer base { ... }` al final del archivo, añadir una regla dentro del layer (después de la regla `body { ... }`):
```css
    h1 {
        font-family: var(--font-display);
    }
```

- [ ] **Step 5: Verificar build**

Run: `npm run build`
Expected: build exitoso; en la salida del bundle CSS debe aparecer que se referencia Chakra Petch (la directiva `@fonts` la inyecta en runtime vía Bunny). Sin errores.

- [ ] **Step 6: Commit**

```bash
git add resources/css/app.css
git commit -m "feat(tema): paleta Electrico (azul/cian + navy) y fuente display Chakra Petch"
```

---

## Task 2: Modo oscuro por defecto

**Files:**
- Modify: `resources/js/hooks/use-appearance.tsx`

- [ ] **Step 1: Cambiar el default a oscuro en `getStoredAppearance`**

En `resources/js/hooks/use-appearance.tsx`, en la función `getStoredAppearance`, cambiar:
```ts
    return (localStorage.getItem('appearance') as Appearance) || 'system';
```
por:
```ts
    return (localStorage.getItem('appearance') as Appearance) || 'dark';
```

- [ ] **Step 2: Cambiar el default en `initializeTheme`**

En `initializeTheme`, reemplazar el bloque:
```ts
    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }
```
por:
```ts
    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'dark');
        setCookie('appearance', 'dark');
    }
```
(El toggle en Ajustes sigue permitiendo `claro`/`oscuro`/`system` y persiste la elección; solo cambia el valor inicial cuando no hay preferencia guardada.)

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/hooks/use-appearance.tsx
git commit -m "feat(tema): modo oscuro por defecto (toggle claro intacto)"
```

---

## Task 3: Marca RoboLeague (logo, ícono, nombre)

**Files:**
- Modify: `resources/js/components/app-logo-icon.tsx`
- Modify: `resources/js/components/app-logo.tsx`
- Modify: `.env`, `.env.example`

- [ ] **Step 1: Ícono robótico (lucide `Bot`)**

Reemplazar `resources/js/components/app-logo-icon.tsx` por:
```tsx
import { Bot } from 'lucide-react';
import type { ComponentProps } from 'react';

export default function AppLogoIcon(props: ComponentProps<typeof Bot>) {
    return <Bot {...props} />;
}
```

- [ ] **Step 2: Wordmark RoboLeague con acento + fuente display**

Reemplazar `resources/js/components/app-logo.tsx` por:
```tsx
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-display font-semibold leading-tight tracking-wide">
                    RoboLeague
                </span>
            </div>
        </>
    );
}
```
(La utilidad `font-display` la genera Tailwind v4 a partir del token `--font-display` de la Task 1.)

- [ ] **Step 3: Nombre de la app**

En `.env` y en `.env.example`, cambiar la línea `APP_NAME=Laravel` por:
```dotenv
APP_NAME=RoboLeague
```
(Alimenta los `<title>` "… - RoboLeague" vía `VITE_APP_NAME="${APP_NAME}"` y el header de auth. `.env` está gitignored; solo se commitea `.env.example`.)

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS (lucide `Bot` y `font-display` resuelven).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/app-logo-icon.tsx resources/js/components/app-logo.tsx .env.example
git commit -m "feat(marca): wordmark e icono RoboLeague y APP_NAME"
```

---

## Task 4: Verificación integral de C0

**Files:** ninguno.

- [ ] **Step 1: Suite de pruebas (red de seguridad)**

Run: `php artisan test --compact`
Expected: 139/139 PASS (los cambios de CSS/marca/config no afectan el backend).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes (apenas se tocó PHP; solo `.env`/config no aplica a Pint).

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual manual**

Con `composer run dev` (o `npm run dev`): abrir la app y confirmar:
- Sidebar muestra **"RoboLeague"** con el ícono en cuadro azul.
- Acento azul en botones primarios, foco y elemento activo del sidebar.
- Modo **oscuro por defecto** (navy) en una sesión sin preferencia previa; el toggle en Ajustes cambia a claro y persiste.
- Títulos de página (`h1`, p. ej. "Inscripciones", "Reportes") y el wordmark en **Chakra Petch**.
- `<title>` del navegador termina en "- RoboLeague".

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(tema): verificacion integral C0" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Tokens Eléctrico claro+oscuro (navy real) → Task 1 ✓
- Fuente `--font-display: Chakra Petch` (auto `@fonts`) + aplicada a marca y `h1` → Tasks 1, 3 ✓
- Oscuro por defecto con toggle claro intacto → Task 2 ✓
- Marca RoboLeague (wordmark + ícono) + `APP_NAME` → Task 3 ✓
- Verificación: 139 tests, build, pint, visual → Task 4 ✓

**Notas/riesgos:**
- Si el build falla porque `font-display` no existe como utilidad, confirmar que el token `--font-display` quedó dentro de `@theme` (Tailwind v4 genera la utilidad desde ahí).
- Posible "flash" inicial de tema en la primera carga si `app.blade.php` tuviera un script inline de apariencia con default 'system'; si se nota, alinear ese default a 'dark' (verificación visual del Step 4 lo detectaría). No se anticipa, pero queda anotado.
- Los valores OKLCH son guía; si algún contraste se ve bajo en la revisión visual, ajustar el lightness del token correspondiente (no requiere cambио estructural).
