import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart3, Bot, Boxes, ClipboardCheck, ClipboardList, ClipboardX, DollarSign, Swords, Timer, Trophy, type LucideIcon } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import QuickActionCard from '@/components/quick-action-card';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { estadoBadgeVariant } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Auth } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    BarChart3, Bot, ClipboardCheck, ClipboardList, ClipboardX, Swords, Timer, Boxes, DollarSign, Trophy,
};

function iconParaStat(label: string): LucideIcon {
    if (label.includes('recaudado')) return DollarSign;
    if (label.includes('Inspeccion')) return ClipboardCheck;
    if (label.includes('Encuentro')) return Swords;
    if (label.includes('robot') || label.includes('Robots')) return Bot;
    return Boxes;
}

type Stat = { label: string; value: string | number };
type RobotRow = {
    id_robot: number;
    nombre: string;
    categoria: string | null;
    estado_pago: string;
};
type AccionRapida = { label: string; href: string; icon: string };
type AtencionItem = { label: string; value: number; href: string; tone: string };

type DashboardProps = {
    auth: Auth;
    stats: Stat[];
    robots?: RobotRow[];
    accionesRapidas?: AccionRapida[];
    atencion?: AtencionItem[];
};

export default function Dashboard() {
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
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
