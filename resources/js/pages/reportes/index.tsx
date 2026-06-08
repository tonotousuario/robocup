import { Head, usePage } from '@inertiajs/react';
import StatCard from '@/components/stat-card';
import reportes from '@/routes/reportes';
import type { EmparejamientoVigente, PosicionReporte, ReporteCaja } from '@/types';

type PageProps = {
    puedeVerCaja: boolean;
    caja: ReporteCaja | null;
    posiciones: PosicionReporte[];
    emparejamientos: EmparejamientoVigente[];
};

function agrupar<T, K extends string | number>(items: T[], clave: (item: T) => K): Map<K, T[]> {
    const mapa = new Map<K, T[]>();
    for (const item of items) {
        const k = clave(item);
        const grupo = mapa.get(k) ?? [];
        grupo.push(item);
        mapa.set(k, grupo);
    }
    return mapa;
}

export default function ReportesIndex() {
    const { puedeVerCaja, caja, posiciones, emparejamientos } = usePage<PageProps>().props;

    const posicionesPorCategoria = agrupar(posiciones, (p) => p.categoria ?? '—');
    const emparejamientosPorCategoria = agrupar(emparejamientos, (e) => e.categoria ?? '—');

    return (
        <>
            <Head title="Reportes" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                {puedeVerCaja && caja && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold">Caja</h2>
                        <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                            <StatCard label="Total recaudado" value={`$${caja.total_recaudado}`} />
                            <StatCard label="Pagadas" value={caja.pagadas} />
                            <StatCard label="Pendientes" value={caja.pendientes} />
                            <StatCard label="Canceladas" value={caja.canceladas} />
                        </div>
                        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <tr>
                                        <th scope="col" className="p-3">Categoría</th>
                                        <th scope="col" className="p-3">Pagadas</th>
                                        <th scope="col" className="p-3">Recaudado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {caja.por_categoria.map((fila) => (
                                        <tr key={fila.categoria} className="border-b border-sidebar-border/40 last:border-0">
                                            <td className="p-3">{fila.categoria}</td>
                                            <td className="p-3">{fila.pagadas}</td>
                                            <td className="p-3">${fila.recaudado}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold">Posiciones</h2>
                    {posicionesPorCategoria.size === 0 ? (
                        <p className="text-muted-foreground">Aún no hay tiempos registrados.</p>
                    ) : (
                        [...posicionesPorCategoria.entries()].map(([categoria, filas]) => (
                            <div key={categoria}>
                                <h3 className="mb-2 text-sm font-medium text-muted-foreground">{categoria}</h3>
                                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                            <tr>
                                                <th scope="col" className="p-3">#</th>
                                                <th scope="col" className="p-3">Robot</th>
                                                <th scope="col" className="p-3">Mejor tiempo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filas.map((p, i) => (
                                                <tr key={p.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                                    <td className="p-3">{i + 1}</td>
                                                    <td className="p-3">{p.robot ?? '—'}</td>
                                                    <td className="p-3">{p.mejor_tiempo ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))
                    )}
                </section>

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold">Emparejamientos vigentes</h2>
                    {emparejamientosPorCategoria.size === 0 ? (
                        <p className="text-muted-foreground">No hay emparejamientos pendientes.</p>
                    ) : (
                        [...emparejamientosPorCategoria.entries()].map(([categoria, filas]) => (
                            <div key={categoria}>
                                <h3 className="mb-2 text-sm font-medium text-muted-foreground">{categoria}</h3>
                                <ul className="flex flex-col gap-2">
                                    {filas.map((e) => (
                                        <li
                                            key={e.id_encuentro}
                                            className="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                                        >
                                            <span className="text-muted-foreground">{e.ronda}: </span>
                                            {(e.robots[0] ?? '—')} vs {(e.robots[1] ?? '—')}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

ReportesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reportes',
            href: reportes.index(),
        },
    ],
};
