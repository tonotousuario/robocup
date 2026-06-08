import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import RobotController from '@/actions/App/Http/Controllers/RobotController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import RobotFormDialog from '@/components/robots/robot-form-dialog';
import { Button } from '@/components/ui/button';
import robots from '@/routes/robots';
import type { Auth, CategoriaOpcion, InstitucionOpcion, PilotoOpcion, RobotRow } from '@/types';

type PageProps = {
    auth: Auth;
    robots: RobotRow[];
    categorias: CategoriaOpcion[];
    instituciones: InstitucionOpcion[];
    pilotos: PilotoOpcion[];
};

export default function RobotsIndex() {
    const { auth, robots: rows, categorias, instituciones, pilotos } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const destroy = (robot: RobotRow) => {
        router.delete(RobotController.destroy.url(robot.id_robot), {
            preserveScroll: true,
            onError: (errors) => {
                const message = Object.values(errors)[0];
                if (message) {
                    toast.error(message);
                }
            },
        });
    };

    return (
        <>
            <Head title="Robots" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Robots</h1>
                    <RobotFormDialog
                        categorias={categorias}
                        instituciones={instituciones}
                        pilotos={pilotos}
                        trigger={<Button>Nuevo robot</Button>}
                    />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Categoría</th>
                                <th scope="col" className="p-3">Institución</th>
                                {isAdmin && <th scope="col" className="p-3">Piloto</th>}
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={isAdmin ? 5 : 4}>
                                        No hay robots registrados.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((robot) => (
                                    <tr key={robot.id_robot} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{robot.nombre}</td>
                                        <td className="p-3">{robot.categoria ?? '—'}</td>
                                        <td className="p-3">{robot.institucion ?? '—'}</td>
                                        {isAdmin && <td className="p-3">{robot.piloto ?? '—'}</td>}
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <RobotFormDialog
                                                    robot={robot}
                                                    categorias={categorias}
                                                    instituciones={instituciones}
                                                    pilotos={pilotos}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar robot"
                                                    description={`¿Seguro que deseas eliminar "${robot.nombre}"?`}
                                                    onConfirm={() => destroy(robot)}
                                                />
                                            </div>
                                        </td>
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

RobotsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Robots',
            href: robots.index(),
        },
    ],
};
