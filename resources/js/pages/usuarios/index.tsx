import { Head, router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { toast } from 'sonner';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import DataTable, { type Column } from '@/components/data-table/data-table';
import PageHeader from '@/components/page-header';
import UsuarioFormDialog from '@/components/usuarios/usuario-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableQuery } from '@/hooks/use-table-query';
import { estadoBadgeVariant } from '@/lib/utils';
import usuarios from '@/routes/usuarios';
import type { UsuarioRow } from '@/types';
import type { Paginated } from '@/types/pagination';

type PageProps = {
    usuarios: Paginated<UsuarioRow>;
    filtros: { q: string; rol: string; sort: string; dir: string };
};

export default function UsuariosIndex() {
    const { usuarios: page, filtros } = usePage<PageProps>().props;

    const { setBusqueda, setFiltro, setOrden } = useTableQuery(usuarios.index().url, ['usuarios', 'filtros'], filtros);

    const destroy = (usuario: UsuarioRow) => {
        router.delete(UsuarioController.destroy.url(usuario.id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.usuario) {
                    toast.error(errors.usuario);
                }
            },
        });
    };

    const columns: Column<UsuarioRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            sortable: true,
            render: (row) => row.name,
        },
        {
            key: 'apellidos',
            header: 'Apellidos',
            render: (row) => row.apellidos,
        },
        {
            key: 'email',
            header: 'Correo',
            sortable: true,
            render: (row) => row.email,
        },
        {
            key: 'telefono',
            header: 'Teléfono',
            render: (row) => row.telefono ?? '—',
        },
        {
            key: 'rol',
            header: 'Rol',
            sortable: true,
            render: (row) => <Badge variant={estadoBadgeVariant(row.rol)}>{row.rol}</Badge>,
        },
        {
            key: 'acciones',
            header: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <UsuarioFormDialog usuario={row} trigger={<Button variant="secondary" size="sm">Editar</Button>} />
                    <ConfirmDeleteDialog
                        trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                        title="Eliminar usuario"
                        description={`¿Seguro que deseas eliminar a "${row.name} ${row.apellidos}"?`}
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
                placeholder="Buscar nombre, apellidos o correo..."
                defaultValue={filtros.q}
                onChange={(e) => setBusqueda(e.target.value)}
            />
            <Select value={filtros.rol || 'todos'} onValueChange={(v) => setFiltro('rol', v)}>
                <SelectTrigger className="w-44">
                    <SelectValue placeholder="Rol" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todos los roles</SelectItem>
                    <SelectItem value="Administrador">Administrador</SelectItem>
                    <SelectItem value="Juez">Juez</SelectItem>
                    <SelectItem value="Coach">Coach</SelectItem>
                    <SelectItem value="Piloto">Piloto</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PageHeader title="Usuarios" action={<UsuarioFormDialog trigger={<Button>Nuevo usuario</Button>} />} />
                <DataTable
                    columns={columns}
                    page={page}
                    rowKey={(r) => r.id}
                    sort={filtros.sort}
                    dir={filtros.dir}
                    onSort={(k) => setOrden(k, filtros.sort, filtros.dir)}
                    toolbar={toolbar}
                    empty={{
                        icon: Users,
                        title: 'Sin usuarios',
                        description: 'Aún no hay usuarios registrados.',
                    }}
                />
            </div>
        </>
    );
}

UsuariosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: usuarios.index(),
        },
    ],
};
