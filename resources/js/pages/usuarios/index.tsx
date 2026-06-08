import { Head, router, usePage } from '@inertiajs/react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import UsuarioFormDialog from '@/components/usuarios/usuario-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import usuarios from '@/routes/usuarios';
import type { UsuarioRow } from '@/types';

type PageProps = {
    usuarios: UsuarioRow[];
};

export default function UsuariosIndex() {
    const { usuarios: rows } = usePage<PageProps>().props;

    const destroy = (usuario: UsuarioRow) => {
        router.delete(UsuarioController.destroy.url(usuario.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Usuarios</h1>
                    <UsuarioFormDialog trigger={<Button>Nuevo usuario</Button>} />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">Nombre</th>
                                <th scope="col" className="p-3">Correo</th>
                                <th scope="col" className="p-3">Teléfono</th>
                                <th scope="col" className="p-3">Rol</th>
                                <th scope="col" className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={5}>
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((usuario) => (
                                    <tr key={usuario.id} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{usuario.name} {usuario.apellidos}</td>
                                        <td className="p-3">{usuario.email}</td>
                                        <td className="p-3">{usuario.telefono ?? '—'}</td>
                                        <td className="p-3"><Badge variant="secondary">{usuario.rol}</Badge></td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <UsuarioFormDialog
                                                    usuario={usuario}
                                                    trigger={<Button variant="secondary" size="sm">Editar</Button>}
                                                />
                                                <ConfirmDeleteDialog
                                                    trigger={<Button variant="destructive" size="sm">Eliminar</Button>}
                                                    title="Eliminar usuario"
                                                    description={`¿Seguro que deseas eliminar a "${usuario.name} ${usuario.apellidos}"?`}
                                                    onConfirm={() => destroy(usuario)}
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

UsuariosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: usuarios.index(),
        },
    ],
};
