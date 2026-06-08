import { Head, router, usePage } from '@inertiajs/react';
import CapturarTiemposDialog from '@/components/tiempos/capturar-tiempos-dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import tiempos from '@/routes/tiempos';
import type { CategoriaTiempoOpcion, CompetidorTiempo, IntentoTiempoData } from '@/types';

type PageProps = {
    categorias: CategoriaTiempoOpcion[];
    categoriaSeleccionada: number | null;
    competidores: CompetidorTiempo[];
    puedeCapturar: boolean;
};

function celdaVuelta(intentos: IntentoTiempoData[], vuelta: number): string {
    const intento = intentos.find((i) => i.numero_vuelta === vuelta);
    if (!intento) {
        return '—';
    }
    const penal = Number(intento.penalizacion_segundos);
    return penal > 0 ? `${intento.tiempo_logrado} (+${intento.penalizacion_segundos})` : intento.tiempo_logrado;
}

export default function TiemposIndex() {
    const { categorias, categoriaSeleccionada, competidores, puedeCapturar } = usePage<PageProps>().props;

    const cambiarCategoria = (id: string) => {
        router.get(tiempos.index().url, { categoria: id }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Tiempos" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Tiempos y posiciones</h1>
                    {categorias.length > 0 && (
                        <Select value={categoriaSeleccionada ? String(categoriaSeleccionada) : undefined} onValueChange={cambiarCategoria}>
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Categoría" />
                            </SelectTrigger>
                            <SelectContent>
                                {categorias.map((c) => (
                                    <SelectItem key={c.id_categoria} value={String(c.id_categoria)}>
                                        {c.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th scope="col" className="p-3">#</th>
                                <th scope="col" className="p-3">Robot</th>
                                <th scope="col" className="p-3">V1</th>
                                <th scope="col" className="p-3">V2</th>
                                <th scope="col" className="p-3">V3</th>
                                <th scope="col" className="p-3">Mejor</th>
                                {puedeCapturar && <th scope="col" className="p-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {competidores.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={puedeCapturar ? 7 : 6}>
                                        No hay competidores aprobados en esta categoría.
                                    </td>
                                </tr>
                            ) : (
                                competidores.map((c) => (
                                    <tr key={c.id_inscripcion} className="border-b border-sidebar-border/40 last:border-0">
                                        <td className="p-3">{c.posicion ?? '—'}</td>
                                        <td className="p-3">{c.robot ?? '—'}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 1)}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 2)}</td>
                                        <td className="p-3">{celdaVuelta(c.intentos, 3)}</td>
                                        <td className="p-3 font-semibold">{c.mejor_tiempo ?? '—'}</td>
                                        {puedeCapturar && (
                                            <td className="p-3">
                                                <div className="flex justify-end">
                                                    <CapturarTiemposDialog
                                                        competidor={c}
                                                        trigger={<Button variant="secondary" size="sm">Capturar</Button>}
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

TiemposIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tiempos',
            href: tiempos.index(),
        },
    ],
};
