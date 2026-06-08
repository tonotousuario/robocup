import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import InscripcionController from '@/actions/App/Http/Controllers/InscripcionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import InscribirRobotDialog from '@/components/inscripciones/inscribir-robot-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import inscripciones from '@/routes/inscripciones';
import type { Auth, InscripcionRow, RobotInscribible, TarifaVigente } from '@/types';

type PageProps = {
    auth: Auth;
    inscripciones: InscripcionRow[];
    robotsInscribibles: RobotInscribible[];
    tarifaVigente: TarifaVigente | null;
};

const ESTADO_CLASS: Record<InscripcionRow['estado_pago'], string> = {
    Pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Pagado: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Cancelado: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
};

export default function InscripcionesIndex() {
    const { auth, inscripciones: rows, robotsInscribibles, tarifaVigente } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const onError = (errors: Record<string, string>) => {
        const message = Object.values(errors)[0];
        if (message) {
            toast.error(message);
        }
    };

    const pagar = (row: InscripcionRow) => {
        router.patch(InscripcionController.pagar.url(row.id_inscripcion), { preserveScroll: true, onError });
    };

    const cancelar = (row: InscripcionRow) => {
        router.patch(InscripcionController.cancelar.url(row.id_inscripcion), { preserveScroll: true, onError });
    };

    return (
        <>
            <Head title="Inscripciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Inscripciones</h1>
                    <InscribirRobotDialog
                        robots={robotsInscribibles}
                        tarifaVigente={tarifaVigente}
                        trigger={<Button>Inscribir robot</Button>}
                    />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">Categoría</th>
                                {isAdmin && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3">Tarifa</th>
                                <th scope="col" className="p-3">Monto</th>
                                <th scope="col" className="p-3">Estado</th>
                                {isAdmin && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={isAdmin ? 7 : 5}>
                                        No hay inscripciones.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr key={row.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{row.robot ?? '—'}</td>
                                        <td className="p-3">{row.categoria ?? '—'}</td>
                                        {isAdmin && <td className="p-3">{row.piloto ?? '—'}</td>}
                                        <td className="p-3">{row.tarifa ?? '—'}</td>
                                        <td className="p-3">${row.monto_pagado}</td>
                                        <td className="p-3">
                                            <Badge variant="secondary" className={ESTADO_CLASS[row.estado_pago]}>
                                                {row.estado_pago}
                                            </Badge>
                                        </td>
                                        {isAdmin && (
                                            <td className="p-3">
                                                <div className="flex justify-end gap-2">
                                                    {row.estado_pago === 'Pendiente' && (
                                                        <>
                                                            <Button size="sm" onClick={() => pagar(row)}>
                                                                Marcar pagado
                                                            </Button>
                                                            <ConfirmDeleteDialog
                                                                trigger={<Button variant="destructive" size="sm">Cancelar</Button>}
                                                                title="Cancelar inscripción"
                                                                description={`¿Cancelar la inscripción de "${row.robot}"? El robot podrá inscribirse de nuevo.`}
                                                                onConfirm={() => cancelar(row)}
                                                            />
                                                        </>
                                                    )}
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

InscripcionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inscripciones',
            href: inscripciones.index(),
        },
    ],
};
