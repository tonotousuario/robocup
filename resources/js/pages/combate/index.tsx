import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import PanelEncuentro from '@/components/combate/registrar-ganador-control';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import combate from '@/routes/combate';
import type { CategoriaCombateOpcion, EncuentroBracket } from '@/types';

type PageProps = {
    categorias: CategoriaCombateOpcion[];
    categoriaSeleccionada: number | null;
    encuentros: EncuentroBracket[];
    puedeGenerar: boolean;
    puedeRegistrar: boolean;
    aprobadosCount: number;
};

const ORDEN_RONDAS = ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'];

export default function CombateIndex() {
    const { categorias, categoriaSeleccionada, encuentros, puedeGenerar, puedeRegistrar, aprobadosCount } =
        usePage<PageProps>().props;
    const [confirmOpen, setConfirmOpen] = useState(false);

    const cambiarCategoria = (id: string) => {
        router.get(combate.index().url, { categoria: id }, { preserveState: true, preserveScroll: true });
    };

    const generar = () => {
        if (!categoriaSeleccionada) {
            return;
        }
        router.post(
            EncuentroController.generar.url(),
            { id_categoria: categoriaSeleccionada },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmOpen(false),
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    if (message) {
                        toast.error(message);
                    }
                    setConfirmOpen(false);
                },
            },
        );
    };

    const rondas = ORDEN_RONDAS.filter((r) => encuentros.some((e) => e.ronda === r));

    return (
        <>
            <Head title="Combate" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Combate · Brackets</h1>
                    <div className="flex items-center gap-3">
                        {categorias.length > 0 && (
                            <Select
                                value={categoriaSeleccionada ? String(categoriaSeleccionada) : undefined}
                                onValueChange={cambiarCategoria}
                            >
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
                        {puedeGenerar && categoriaSeleccionada && (
                            encuentros.length > 0 ? (
                                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                                    <DialogTrigger asChild>
                                        <Button disabled={aprobadosCount < 2}>Regenerar bracket</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Regenerar bracket</DialogTitle>
                                            <DialogDescription>
                                                Se borrará el bracket actual de esta categoría (incluidos los resultados) y se
                                                creará uno nuevo. ¿Continuar?
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">Cancelar</Button>
                                            </DialogClose>
                                            <Button variant="destructive" onClick={generar}>
                                                Regenerar
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            ) : (
                                <Button onClick={generar} disabled={aprobadosCount < 2}>
                                    Generar bracket
                                </Button>
                            )
                        )}
                    </div>
                </div>

                {puedeGenerar && categoriaSeleccionada && aprobadosCount < 2 && (
                    <p className="text-sm text-muted-foreground">
                        Se requieren al menos 2 robots aprobados para generar el bracket (hay {aprobadosCount}).
                    </p>
                )}

                {encuentros.length === 0 ? (
                    <p className="text-muted-foreground">No hay bracket generado para esta categoría.</p>
                ) : (
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {rondas.map((ronda) => (
                            <div key={ronda} className="flex min-w-56 flex-col gap-4">
                                <h2 className="text-sm font-semibold text-muted-foreground">{ronda}</h2>
                                {encuentros
                                    .filter((e) => e.ronda === ronda)
                                    .map((encuentro) => {
                                        const tieneGanador = encuentro.participantes.some((p) => p.es_ganador);
                                        return (
                                            <div
                                                key={encuentro.id_encuentro}
                                                className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                            >
                                                {encuentro.participantes.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">Por definir</p>
                                                ) : (
                                                    encuentro.participantes.map((p) => (
                                                        <p
                                                            key={p.id_inscripcion}
                                                            className={p.es_ganador ? 'font-semibold' : ''}
                                                        >
                                                            {p.robot ?? '—'} {p.es_ganador ? '✓' : ''}
                                                        </p>
                                                    ))
                                                )}
                                                {puedeRegistrar && encuentro.participantes.length === 2 && !tieneGanador && (
                                                    <PanelEncuentro encuentro={encuentro} />
                                                )}
                                            </div>
                                        );
                                    })}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CombateIndex.layout = {
    breadcrumbs: [
        {
            title: 'Combate',
            href: combate.index(),
        },
    ],
};
