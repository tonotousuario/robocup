import { Head, router, usePage } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import { toast } from 'sonner';
import RobotController from '@/actions/App/Http/Controllers/RobotController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import DataTable, { type Column } from '@/components/data-table/data-table';
import PageHeader from '@/components/page-header';
import RobotFormDialog from '@/components/robots/robot-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableQuery } from '@/hooks/use-table-query';
import robots from '@/routes/robots';
import type { Auth, CategoriaOpcion, InstitucionOpcion, PilotoOpcion, RobotRow } from '@/types';
import type { Paginated } from '@/types/pagination';

type PageProps = {
    auth: Auth;
    robots: Paginated<RobotRow>;
    categorias: CategoriaOpcion[];
    instituciones: InstitucionOpcion[];
    pilotos: PilotoOpcion[];
    filtros: { q: string; categoria: string; sort: string; dir: string };
};

export default function RobotsIndex() {
    const { auth, robots: page, categorias, instituciones, pilotos, filtros } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const { setBusqueda, setFiltro, setOrden } = useTableQuery(robots.index().url, ['robots', 'filtros'], filtros);

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

    const columns: Column<RobotRow>[] = [
        {
            key: 'nombre',
            header: 'Nombre',
            sortable: true,
            render: (row) => row.nombre,
        },
        {
            key: 'categoria',
            header: 'Categoría',
            render: (row) => row.categoria ?? '—',
        },
        {
            key: 'institucion',
            header: 'Institución',
            render: (row) => row.institucion ?? '—',
        },
        ...(isAdmin
            ? ([
                  {
                      key: 'piloto',
                      header: 'Piloto',
                      render: (row: RobotRow) => row.piloto ?? '—',
                  },
              ] satisfies Column<RobotRow>[])
            : []),
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <RobotFormDialog
                        robot={row}
                        categorias={categorias}
                        instituciones={instituciones}
                        pilotos={pilotos}
                        trigger={<Button variant="secondary" size="sm">Editar</Button>}
                    />
                    <ConfirmDeleteDialog
                        trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                        title="Eliminar robot"
                        description={`¿Seguro que deseas eliminar "${row.nombre}"?`}
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
                placeholder="Buscar robot..."
                defaultValue={filtros.q}
                onChange={(e) => setBusqueda(e.target.value)}
            />
            <Select value={filtros.categoria || 'todos'} onValueChange={(v) => setFiltro('categoria', v)}>
                <SelectTrigger className="w-44">
                    <SelectValue placeholder="Categoría" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todas las categorías</SelectItem>
                    {categorias.map((cat) => (
                        <SelectItem key={cat.id_categoria} value={String(cat.id_categoria)}>
                            {cat.nombre}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <>
            <Head title="Robots" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PageHeader
                    title="Robots"
                    action={
                        <RobotFormDialog
                            categorias={categorias}
                            instituciones={instituciones}
                            pilotos={pilotos}
                            trigger={<Button>Nuevo robot</Button>}
                        />
                    }
                />
                <DataTable
                    columns={columns}
                    page={page}
                    rowKey={(r) => r.id_robot}
                    sort={filtros.sort}
                    dir={filtros.dir}
                    onSort={(k) => setOrden(k, filtros.sort, filtros.dir)}
                    toolbar={toolbar}
                    empty={{
                        icon: Bot,
                        title: 'Sin robots',
                        description: 'Aún no hay robots registrados.',
                    }}
                />
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
