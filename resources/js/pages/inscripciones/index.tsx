import { Head, router, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { toast } from 'sonner';
import InscripcionController from '@/actions/App/Http/Controllers/InscripcionController';
import DataTable, { type Column } from '@/components/data-table/data-table';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import InscribirRobotDialog from '@/components/inscripciones/inscribir-robot-dialog';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableQuery } from '@/hooks/use-table-query';
import { estadoBadgeVariant } from '@/lib/utils';
import inscripciones from '@/routes/inscripciones';
import type { Auth, InscripcionRow, RobotInscribible, TarifaVigente } from '@/types';
import type { Paginated } from '@/types/pagination';

type PageProps = {
    auth: Auth;
    inscripciones: Paginated<InscripcionRow>;
    robotsInscribibles: RobotInscribible[];
    tarifaVigente: TarifaVigente | null;
    categorias: { id_categoria: number; nombre: string }[];
    filtros: { q: string; estado: string; categoria: string; sort: string; dir: string };
};

export default function InscripcionesIndex() {
    const { auth, inscripciones: page, robotsInscribibles, tarifaVigente, categorias, filtros } = usePage<PageProps>().props;
    const isAdmin = auth.user.rol === 'Administrador';

    const { setBusqueda, setFiltro, setOrden } = useTableQuery(
        inscripciones.index().url,
        ['inscripciones', 'filtros'],
        filtros,
    );

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

    const columns: Column<InscripcionRow>[] = [
        {
            key: 'robot',
            header: 'Robot',
            render: (row) => row.robot ?? '—',
        },
        {
            key: 'categoria',
            header: 'Categoría',
            render: (row) => row.categoria ?? '—',
        },
        ...(isAdmin
            ? ([
                  {
                      key: 'piloto',
                      header: 'Piloto',
                      render: (row: InscripcionRow) => row.piloto ?? '—',
                  },
              ] satisfies Column<InscripcionRow>[])
            : []),
        {
            key: 'tarifa',
            header: 'Tarifa',
            render: (row) => row.tarifa ?? '—',
        },
        {
            key: 'monto_pagado',
            header: 'Monto',
            sortable: true,
            render: (row) => `$${row.monto_pagado}`,
        },
        {
            key: 'estado_pago',
            header: 'Estado',
            sortable: true,
            render: (row) => (
                <Badge variant={estadoBadgeVariant(row.estado_pago)}>
                    {row.estado_pago}
                </Badge>
            ),
        },
        ...(isAdmin
            ? ([
                  {
                      key: 'acciones',
                      header: 'Acciones',
                      className: 'text-right',
                      render: (row: InscripcionRow) => (
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
                      ),
                  },
              ] satisfies Column<InscripcionRow>[])
            : []),
    ];

    const toolbar = (
        <div className="flex flex-wrap items-center gap-3">
            <Input
                className="max-w-xs"
                placeholder="Buscar robot, piloto o categoría..."
                defaultValue={filtros.q}
                onChange={(e) => setBusqueda(e.target.value)}
            />
            <Select value={filtros.estado || 'todos'} onValueChange={(v) => setFiltro('estado', v)}>
                <SelectTrigger className="w-44">
                    <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todos los estados</SelectItem>
                    <SelectItem value="Pendiente">Pendiente</SelectItem>
                    <SelectItem value="Pagado">Pagado</SelectItem>
                    <SelectItem value="Cancelado">Cancelado</SelectItem>
                </SelectContent>
            </Select>
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
            <Head title="Inscripciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PageHeader
                    title="Inscripciones"
                    action={
                        <InscribirRobotDialog
                            robots={robotsInscribibles}
                            tarifaVigente={tarifaVigente}
                            trigger={<Button>Inscribir robot</Button>}
                        />
                    }
                />
                <DataTable
                    columns={columns}
                    page={page}
                    rowKey={(r) => r.id_inscripcion}
                    sort={filtros.sort}
                    dir={filtros.dir}
                    onSort={(k) => setOrden(k, filtros.sort, filtros.dir)}
                    toolbar={toolbar}
                    empty={{
                        icon: ClipboardList,
                        title: 'Sin inscripciones',
                        description: 'Aún no hay inscripciones registradas.',
                    }}
                />
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
