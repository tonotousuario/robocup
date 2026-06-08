import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import InstitucionController from '@/actions/App/Http/Controllers/InstitucionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import InstitucionFormDialog from '@/components/instituciones/institucion-form-dialog';
import { Button } from '@/components/ui/button';
import instituciones from '@/routes/instituciones';
import type { Institucion } from '@/types';

type PageProps = {
    instituciones: Institucion[];
};

export default function InstitucionesIndex() {
    const { instituciones: rows } = usePage<PageProps>().props;

    const destroy = (institucion: Institucion) => {
        router.delete(InstitucionController.destroy.url(institucion.id_institucion), {
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
            <Head title="Instituciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Instituciones</h1>
                    <InstitucionFormDialog trigger={<Button>Nueva institución</Button>} />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Tipo</th>
                                <th scope="col" className="p-3">Estado</th>
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={4}>
                                        No hay instituciones registradas.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((institucion) => (
                                    <tr key={institucion.id_institucion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{institucion.nombre}</td>
                                        <td className="p-3">{institucion.tipo}</td>
                                        <td className="p-3">{institucion.estado}</td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <InstitucionFormDialog
                                                    institucion={institucion}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar institución"
                                                    description={`¿Seguro que deseas eliminar "${institucion.nombre}"? Los robots asociados quedarán sin institución.`}
                                                    onConfirm={() => destroy(institucion)}
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

InstitucionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Instituciones',
            href: instituciones.index(),
        },
    ],
};
