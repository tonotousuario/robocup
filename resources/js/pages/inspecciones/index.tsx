import { Head, usePage } from '@inertiajs/react';
import InspeccionarDialog from '@/components/inspecciones/inspeccionar-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import inspecciones from '@/routes/inspecciones';
import type { InspeccionEstado, InspeccionListItem } from '@/types';

type PageProps = {
    inspecciones: InspeccionListItem[];
    puedeInspeccionar: boolean;
};

const ESTADO_CLASS: Record<InspeccionEstado, string> = {
    Pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Aprobado: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Rechazado: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    Descalificado: 'bg-neutral-300 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200',
};

export default function InspeccionesIndex() {
    const { inspecciones: rows, puedeInspeccionar } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Inspección" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Inspección técnica</h1>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">Categoría</th>
                                {puedeInspeccionar && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3">Estado</th>
                                {puedeInspeccionar && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={puedeInspeccionar ? 5 : 3}>
                                        No hay inscripciones para inspeccionar.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((item) => (
                                    <tr key={item.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{item.robot ?? '—'}</td>
                                        <td className="p-3">{item.categoria ?? '—'}</td>
                                        {puedeInspeccionar && <td className="p-3">{item.piloto ?? '—'}</td>}
                                        <td className="p-3">
                                            <Badge variant="secondary" className={ESTADO_CLASS[item.estado]}>
                                                {item.estado}
                                            </Badge>
                                        </td>
                                        {puedeInspeccionar && (
                                            <td className="p-3">
                                                <div className="flex justify-end">
                                                    <InspeccionarDialog
                                                        item={item}
                                                        trigger={
                                                            <Button variant="secondary" size="sm">
                                                                {item.inspeccion ? 'Re-inspeccionar' : 'Inspeccionar'}
                                                            </Button>
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

InspeccionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inspección',
            href: inspecciones.index(),
        },
    ],
};
