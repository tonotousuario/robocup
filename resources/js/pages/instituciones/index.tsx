import { Head, router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { toast } from 'sonner';
import InstitucionController from '@/actions/App/Http/Controllers/InstitucionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import DataTable, { type Column } from '@/components/data-table/data-table';
import InstitucionFormDialog from '@/components/instituciones/institucion-form-dialog';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTableQuery } from '@/hooks/use-table-query';
import instituciones from '@/routes/instituciones';
import type { InstitucionRow } from '@/types';
import type { Paginated } from '@/types/pagination';

type PageProps = {
    instituciones: Paginated<InstitucionRow>;
    filtros: { q: string; sort: string; dir: string };
};

export default function InstitucionesIndex() {
    const { instituciones: page, filtros } = usePage<PageProps>().props;

    const { setBusqueda, setOrden } = useTableQuery(instituciones.index().url, ['instituciones', 'filtros'], filtros);

    const destroy = (institucion: InstitucionRow) => {
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

    const columns: Column<InstitucionRow>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            sortable: true,
            render: (row) => row.nombre,
        },
        {
            key: 'tipo',
            header: 'Tipo',
            render: (row) => row.tipo,
        },
        {
            key: 'estado',
            header: 'Estado',
            render: (row) => row.estado,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <InstitucionFormDialog
                        institucion={row}
                        trigger={<Button variant="secondary" size="sm">Editar</Button>}
                    />
                    <ConfirmDeleteDialog
                        trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                        title="Eliminar institución"
                        description={`¿Seguro que deseas eliminar "${row.nombre}"? Los robots asociados quedarán sin institución.`}
                        onConfirm={() => destroy(row)}
                    />
                </div>
            ),
        },
    ];

    const toolbar = (
        <div className="flex flex-wrap items-center gap-3">
            <Input
                className="max-w-xs"
                placeholder="Buscar institución..."
                defaultValue={filtros.q}
                onChange={(e) => setBusqueda(e.target.value)}
            />
        </div>
    );

    return (
        <>
            <Head title="Instituciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PageHeader
                    title="Instituciones"
                    action={<InstitucionFormDialog trigger={<Button>Nueva institución</Button>} />}
                />
                <DataTable
                    columns={columns}
                    page={page}
                    rowKey={(r) => r.id_institucion}
                    sort={filtros.sort}
                    dir={filtros.dir}
                    onSort={(k) => setOrden(k, filtros.sort, filtros.dir)}
                    toolbar={toolbar}
                    empty={{
                        icon: Building2,
                        title: 'Sin instituciones',
                        description: 'Aún no hay instituciones registradas.',
                    }}
                />
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
