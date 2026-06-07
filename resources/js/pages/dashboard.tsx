import { Head, usePage } from '@inertiajs/react';
import StatCard from '@/components/stat-card';
import { dashboard } from '@/routes';
import type { Auth } from '@/types';

type Stat = { label: string; value: string | number };
type RobotRow = {
    id_robot: number;
    nombre: string;
    categoria: string | null;
    estado_pago: string;
};

type DashboardProps = {
    auth: Auth;
    stats: Stat[];
    robots?: RobotRow[];
};

export default function Dashboard() {
    const { auth, stats, robots } = usePage<DashboardProps>().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Hola, {auth.user.name}</h1>
                    <p className="text-sm text-muted-foreground">Rol: {auth.user.rol}</p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {stats.map((stat) => (
                        <StatCard key={stat.label} label={stat.label} value={stat.value} />
                    ))}
                </div>

                {robots && (
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <tr>
                                    <th className="p-3">Robot</th>
                                    <th className="p-3">Categoría</th>
                                    <th className="p-3">Estado de pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                {robots.length === 0 ? (
                                    <tr>
                                        <td className="p-3 text-muted-foreground" colSpan={3}>
                                            Aún no tienes robots registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    robots.map((robot) => (
                                        <tr key={robot.id_robot} className="border-b border-sidebar-border/40 last:border-0">
                                            <td className="p-3">{robot.nombre}</td>
                                            <td className="p-3">{robot.categoria ?? '—'}</td>
                                            <td className="p-3">{robot.estado_pago}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
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
