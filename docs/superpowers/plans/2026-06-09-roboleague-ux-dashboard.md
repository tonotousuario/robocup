# RoboLeague — Refinamiento UX: Dashboard por rol + primitivas · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) o superpowers:executing-plans. REQUIRED para tareas de UI: usar la skill **frontend-design** al construir/visualizar componentes. Los pasos usan checkbox (`- [ ]`).

**Goal:** Enriquecer el dashboard por rol con accesos rápidos y un panel "qué atender", y refinarlo visualmente con primitivas de UI reutilizables.

**Architecture:** El `DashboardController` (ya por rol) añade props `accionesRapidas` y `atencion` por rol. Se crean primitivas presentacionales (StatCard mejorado, EmptyState, PageHeader, QuickActionCard) + un helper `estadoBadgeVariant`. `dashboard.tsx` se rehace usándolas. La skill frontend-design guía el diseño visual; Playwright verifica el resultado real.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4 (tema Eléctrico), lucide-react, shadcn ui (Badge ya existe), PHPUnit 12, Pint, Playwright MCP.

**Convenciones/contexto (verificado):**
- Baseline: 175 pruebas.
- `DashboardController::index` → `match ($user->rol)`: `adminStats()`, `juezStats()`, `robotOwnerStats($user)`. Devuelve `{stats:[{label,value}], robots?:[...]}`. `RolUsuario` enum: Administrador/Juez/Coach/Piloto.
- `dashboard.tsx` usa `StatCard` (label+value) en grilla; tabla de robots para Coach/Piloto.
- `StatCard` (`resources/js/components/stat-card.tsx`) hoy: solo `label`+`value`.
- `Badge` existe en `resources/js/components/ui/badge.tsx`. `@/lib/utils.ts` tiene `cn`. lucide-react disponible.
- Rutas nombradas: `inscripciones.index`, `reportes.index`, `combate.index`, `inspecciones.index`, `tiempos.index`, `robots.index`. Wayfinder en `@/routes`/`@/actions` (gitignored). En React, importar `{ dashboard }` etc. desde `@/routes` ya se usa.
- `DashboardTest` existe con 3 tests (admin métricas, coach solo sus robots, juez inspecciones). NO romperlos.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Frontend gate `npm run build`. Tests `php artisan test --compact --filter=...`. Dev server para Playwright: `php artisan serve --port=8000` (ya corriendo); login admin@roboleague.test/password, juez@roboleague.test/password si existe.

---

## File Structure

**Frontend (primitivas):**
- Modify: `resources/js/components/stat-card.tsx` (icon + tone + hint, retrocompatible)
- Create: `resources/js/components/empty-state.tsx`
- Create: `resources/js/components/page-header.tsx`
- Create: `resources/js/components/quick-action-card.tsx`
- Modify: `resources/js/lib/utils.ts` (añadir `estadoBadgeVariant`)
- Modify: `resources/js/pages/dashboard.tsx` (rehacer con primitivas)

**Backend:**
- Modify: `app/Http/Controllers/DashboardController.php` (props `accionesRapidas` + `atencion` por rol)
- Test: `tests/Feature/DashboardTest.php` (extender)

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-ux-dashboard
```
Expected: `Switched to a new branch 'feature/roboleague-ux-dashboard'`.

---

## Task 1: Backend — props `accionesRapidas` y `atencion` por rol

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Añadir tests que fallan**

Añadir a `tests/Feature/DashboardTest.php` (reusa imports existentes; añade `use App\Models\Inscripcion;`, `use App\Models\Categoria;`):
```php
public function test_admin_recibe_acciones_rapidas_y_atencion(): void
{
    Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);
    $admin = User::factory()->create(['rol' => 'Administrador']);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('accionesRapidas')
            ->has('atencion')
            ->where('atencion', fn ($a) => collect($a)->contains(fn ($i) => $i['value'] >= 1))
        );
}

public function test_juez_recibe_encuentros_por_resolver_en_atencion(): void
{
    $juez = User::factory()->juez()->create();

    $this->actingAs($juez)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('accionesRapidas')
            ->has('atencion')
        );
}

public function test_piloto_recibe_acciones_rapidas(): void
{
    $piloto = User::factory()->create(['rol' => 'Piloto']);

    $this->actingAs($piloto)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('accionesRapidas')
            ->has('robots')
        );
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: FAIL (no existen `accionesRapidas`/`atencion`).

- [ ] **Step 3: Enriquecer `DashboardController`**

En `app/Http/Controllers/DashboardController.php`:
- Añadir `use Illuminate\Support\Facades\Route;` no es necesario; usar el helper `route()` global.
- En `adminStats()`, devolver además:
```php
            'accionesRapidas' => [
                ['label' => 'Inscribir robot', 'href' => route('inscripciones.index'), 'icon' => 'ClipboardList'],
                ['label' => 'Reportes y caja', 'href' => route('reportes.index'), 'icon' => 'BarChart3'],
                ['label' => 'Combate', 'href' => route('combate.index'), 'icon' => 'Swords'],
            ],
            'atencion' => [
                ['label' => 'Inscripciones pendientes de pago', 'value' => Inscripcion::where('estado_pago', 'Pendiente')->count(), 'href' => route('inscripciones.index'), 'tone' => 'warning'],
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count(), 'href' => route('inspecciones.index'), 'tone' => 'warning'],
            ],
```
- En `juezStats()`, devolver además:
```php
            'accionesRapidas' => [
                ['label' => 'Inspección', 'href' => route('inspecciones.index'), 'icon' => 'ClipboardCheck'],
                ['label' => 'Combate', 'href' => route('combate.index'), 'icon' => 'Swords'],
                ['label' => 'Tiempos', 'href' => route('tiempos.index'), 'icon' => 'Timer'],
            ],
            'atencion' => [
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count(), 'href' => route('inspecciones.index'), 'tone' => 'warning'],
                ['label' => 'Encuentros por resolver', 'value' => Encuentro::whereDoesntHave('participantes', fn ($q) => $q->where('es_ganador', true))->count(), 'href' => route('combate.index'), 'tone' => 'accent'],
            ],
```
- En `robotOwnerStats(User $user)`, devolver además:
```php
            'accionesRapidas' => [
                ['label' => 'Mis robots', 'href' => route('robots.index'), 'icon' => 'Bot'],
                ['label' => 'Inscripciones', 'href' => route('inscripciones.index'), 'icon' => 'ClipboardList'],
            ],
```
Actualizar los PHPDoc `@return` de cada método para incluir las nuevas claves (array shapes), p. ej. `accionesRapidas: array<int, array{label: string, href: string, icon: string}>` y `atencion: array<int, array{label: string, value: int, href: string, tone: string}>`.

- [ ] **Step 4: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: PASS (6 tests: 3 previos + 3 nuevos).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "feat(dashboard): acciones rapidas y panel de atencion por rol"
```

---

## Task 2: Primitivas de UI

**REQUIRED SUB-SKILL:** usar **frontend-design** para el diseño visual de estas primitivas (estética distintiva, no genérica), respetando el tema Eléctrico existente.

**Files:**
- Modify: `resources/js/components/stat-card.tsx`
- Create: `resources/js/components/empty-state.tsx`
- Create: `resources/js/components/page-header.tsx`
- Create: `resources/js/components/quick-action-card.tsx`
- Modify: `resources/js/lib/utils.ts`

- [ ] **Step 1: `estadoBadgeVariant` en utils**

En `resources/js/lib/utils.ts`, añadir (junto al `cn` existente):
```ts
export function estadoBadgeVariant(estado: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (estado) {
        case 'Pagado':
        case 'Aprobado':
            return 'default';
        case 'Pendiente':
            return 'secondary';
        case 'Rechazado':
        case 'Descalificado':
            return 'destructive';
        default:
            return 'outline';
    }
}
```
(Usa las variantes reales del `Badge` del proyecto; si `Badge` no tiene esas variantes, ajustar a las disponibles — verificar `components/ui/badge.tsx` y mapear a clases si hace falta.)

- [ ] **Step 2: `StatCard` mejorado (retrocompatible)**

Reemplazar `resources/js/components/stat-card.tsx` por:
```tsx
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'accent' | 'success' | 'warning' | 'danger';

type StatCardProps = {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    tone?: Tone;
    hint?: string;
};

const toneClasses: Record<Tone, string> = {
    default: 'text-foreground',
    accent: 'text-primary',
    success: 'text-emerald-400',
    warning: 'text-amber-400',
    danger: 'text-destructive',
};

export default function StatCard({ label, value, icon: Icon, tone = 'default', hint }: StatCardProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-5 transition-colors hover:border-primary/50 dark:border-sidebar-border">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{label}</p>
                {Icon ? <Icon className={cn('size-5', toneClasses[tone])} /> : null}
            </div>
            <p className={cn('mt-2 text-3xl font-bold', toneClasses[tone])}>{value}</p>
            {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
        </div>
    );
}
```
(Sigue aceptando solo `label`+`value`.)

- [ ] **Step 3: `EmptyState`**

`resources/js/components/empty-state.tsx`:
```tsx
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EmptyStateProps = {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: { label: string; href: string };
};

export default function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center">
            <Icon className="size-10 text-muted-foreground" />
            <p className="font-display text-lg font-semibold">{title}</p>
            {description ? <p className="max-w-sm text-sm text-muted-foreground">{description}</p> : null}
            {action ? (
                <Button asChild size="sm" className="mt-2">
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            ) : null}
        </div>
    );
}
```

- [ ] **Step 4: `PageHeader`**

`resources/js/components/page-header.tsx`:
```tsx
import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export default function PageHeader({ title, description, action }: PageHeaderProps) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div>
                <h1 className="font-display text-2xl font-bold tracking-tight">{title}</h1>
                {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}
```

- [ ] **Step 5: `QuickActionCard`**

`resources/js/components/quick-action-card.tsx`:
```tsx
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

type QuickActionCardProps = {
    icon: LucideIcon;
    label: string;
    href: string;
};

export default function QuickActionCard({ icon: Icon, label, href }: QuickActionCardProps) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:border-primary hover:bg-primary/5"
        >
            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                <Icon className="size-5" />
            </span>
            <span className="font-medium">{label}</span>
        </Link>
    );
}
```

- [ ] **Step 6: Verificar build**

Run: `npm run build`
Expected: exitoso (las primitivas compilan aunque aún sin usar todas).

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/stat-card.tsx resources/js/components/empty-state.tsx resources/js/components/page-header.tsx resources/js/components/quick-action-card.tsx resources/js/lib/utils.ts
git commit -m "feat(ui): primitivas StatCard mejorado, EmptyState, PageHeader, QuickActionCard"
```

---

## Task 3: Rehacer `dashboard.tsx` con las primitivas

**REQUIRED SUB-SKILL:** usar **frontend-design** para la composición visual (jerarquía, ritmo, acento), sobre el tema Eléctrico.

**Files:**
- Modify: `resources/js/pages/dashboard.tsx`

- [ ] **Step 1: Mapa de iconos por stat/acción**

En `dashboard.tsx`, importar lucide y definir un mapa de nombre→icono para resolver el `icon: string` que viene del backend, y un mapa para los stats conocidos. Importar primitivas:
```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart3, Bot, ClipboardCheck, ClipboardList, Swords, Timer, Boxes, DollarSign, ClipboardX, Trophy, type LucideIcon } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import QuickActionCard from '@/components/quick-action-card';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { estadoBadgeVariant } from '@/lib/utils';
import type { Auth } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    BarChart3, Bot, ClipboardCheck, ClipboardList, Swords, Timer, Boxes, DollarSign, ClipboardX, Trophy,
};

function iconParaStat(label: string): LucideIcon {
    if (label.includes('recaudado')) return DollarSign;
    if (label.includes('Inspeccion')) return ClipboardCheck;
    if (label.includes('Encuentro')) return Swords;
    if (label.includes('robot') || label.includes('Robots')) return Bot;
    return Boxes;
}
```

- [ ] **Step 2: Tipos de props nuevas**

Añadir a `DashboardProps`:
```tsx
type AccionRapida = { label: string; href: string; icon: string };
type AtencionItem = { label: string; value: number; href: string; tone: string };

type DashboardProps = {
    auth: Auth;
    stats: Stat[];
    robots?: RobotRow[];
    accionesRapidas?: AccionRapida[];
    atencion?: AtencionItem[];
};
```
(Mantener `Stat`/`RobotRow` existentes.)

- [ ] **Step 3: Render con primitivas**

Reemplazar el cuerpo del `return` por:
```tsx
    const { auth, stats, robots, accionesRapidas, atencion } = usePage<DashboardProps>().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <PageHeader
                    title={`Hola, ${auth.user.name}`}
                    description={`Rol: ${auth.user.rol}`}
                />

                <div className="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.label}
                            label={stat.label}
                            value={stat.value}
                            icon={iconParaStat(stat.label)}
                            tone={stat.label.includes('recaudado') ? 'success' : 'default'}
                        />
                    ))}
                </div>

                {accionesRapidas && accionesRapidas.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-display text-lg font-semibold">Accesos rápidos</h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {accionesRapidas.map((a) => (
                                <QuickActionCard key={a.label} icon={ICONS[a.icon] ?? Boxes} label={a.label} href={a.href} />
                            ))}
                        </div>
                    </section>
                )}

                {atencion && atencion.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-display text-lg font-semibold">Qué atender</h2>
                        {atencion.every((i) => i.value === 0) ? (
                            <EmptyState icon={Trophy} title="Todo al día" description="No hay pendientes por ahora." />
                        ) : (
                            <div className="flex flex-col gap-2">
                                {atencion.filter((i) => i.value > 0).map((i) => (
                                    <Link
                                        key={i.label}
                                        href={i.href}
                                        className="flex items-center justify-between rounded-xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:border-primary"
                                    >
                                        <span>{i.label}</span>
                                        <Badge variant={i.tone === 'warning' ? 'secondary' : 'default'}>{i.value}</Badge>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {robots && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-display text-lg font-semibold">Mis robots</h2>
                        {robots.length === 0 ? (
                            <EmptyState icon={Bot} title="Aún no tienes robots" description="Registra tu primer robot para inscribirlo." />
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border">
                                        <tr>
                                            <th className="p-3 font-medium">Robot</th>
                                            <th className="p-3 font-medium">Categoría</th>
                                            <th className="p-3 font-medium">Estado de pago</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {robots.map((robot) => (
                                            <tr key={robot.id_robot} className="border-b border-sidebar-border/40 transition-colors last:border-0 hover:bg-muted/40">
                                                <td className="p-3">{robot.nombre}</td>
                                                <td className="p-3">{robot.categoria ?? '—'}</td>
                                                <td className="p-3">
                                                    <Badge variant={estadoBadgeVariant(robot.estado_pago)}>{robot.estado_pago}</Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/dashboard.tsx
git commit -m "feat(dashboard): rehacer con primitivas — stat cards, accesos rapidos, que atender"
```

---

## Task 4: Verificación integral + visual (Playwright)

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (175 baseline + 3 nuevos de DashboardTest = 178).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual con Playwright**

Con el dev server en `http://localhost:8000` (si no corre: `php artisan serve --port=8000 &`):
- Login como `admin@roboleague.test` / `password`; navegar a `/dashboard`; `browser_take_screenshot` (fullPage) → confirmar: header Chakra Petch, stat cards con icono/tono (recaudado en verde), sección "Accesos rápidos" con tarjetas navegables, panel "Qué atender" con badges.
- Si existe `juez@roboleague.test`/`password`, repetir como Juez (debe ver atajos de Inspección/Combate/Tiempos + encuentros por resolver). Si no existe, crearlo por tinker para la captura.
- Confirmar que no hay regresión visual del sidebar/tema.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(dashboard): verificacion visual y ajustes" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Primitivas StatCard (mejorado), EmptyState, PageHeader, QuickActionCard + `estadoBadgeVariant` → Task 2 ✓
- `DashboardController` expone `accionesRapidas` + `atencion` por rol → Task 1 (tests) ✓
- Dashboard refinado (header, stat cards icono/tono, accesos rápidos, "qué atender" con EmptyState, tabla con badges) → Task 3 ✓
- Verificación visual Playwright por rol → Task 4 ✓
- DoD: suite 100%, build, pint → Task 4 ✓
- frontend-design usada en Tasks 2-3 ✓

**Notas/riesgos:**
- (Retrocompat StatCard) los 3 tests existentes de DashboardTest solo verifican `stats`/`robots`; añadir props no los rompe. StatCard sigue aceptando solo label+value.
- (Variantes de Badge) verificar las variantes reales de `components/ui/badge.tsx`; si difieren de default/secondary/destructive/outline, ajustar `estadoBadgeVariant` a las existentes.
- (route() en controlador) devuelve URLs absolutas; el front las usa tal cual en `<Link href>`. Alternativa Wayfinder no necesaria aquí.
- (Sin migración) no requiere `php artisan migrate` en dev.
- (Playwright) si el juez de prueba no existe, crearlo por tinker antes de la captura; no afecta tests (usan factories).
