import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EncuentroBracket } from '@/types';

type Props = {
    encuentro: EncuentroBracket;
};

const onError = (errors: Record<string, string>) => {
    const message = Object.values(errors)[0];
    if (message) {
        toast.error(message);
    }
};

export default function PanelEncuentro({ encuentro }: Props) {
    const [motivo, setMotivo] = useState('');
    const [amonestado, setAmonestado] = useState<number | null>(null);

    const round = (idGanador: number | null, repetido = false) => {
        router.patch(
            EncuentroController.registrarRound.url(encuentro.id_encuentro),
            { id_inscripcion_ganador: idGanador, repetido },
            { preserveScroll: true, onError },
        );
    };

    const accion = (ruta: string, idInscripcion: number) => {
        router.patch(ruta, { id_inscripcion: idInscripcion }, { preserveScroll: true, onError });
    };

    const amonestar = (idInscripcion: number) => {
        router.post(
            EncuentroController.amonestar.url(encuentro.id_encuentro),
            { id_inscripcion: idInscripcion, motivo },
            {
                preserveScroll: true,
                onError,
                onSuccess: () => {
                    setMotivo('');
                    setAmonestado(null);
                },
            },
        );
    };

    return (
        <div className="mt-2 flex flex-col gap-2 border-t border-sidebar-border/40 pt-2">
            <p className="text-xs text-muted-foreground">
                Marcador:{' '}
                {encuentro.participantes
                    .map((p) => `${p.robot ?? '—'} ${encuentro.marcador[String(p.id_inscripcion)] ?? 0}`)
                    .join(' · ')}
            </p>

            <div className="flex flex-wrap gap-1">
                {encuentro.participantes.map((p) => (
                    <Button key={`round-${p.id_inscripcion}`} size="sm" variant="secondary" onClick={() => round(p.id_inscripcion)}>
                        Gana round {p.robot ?? '—'}
                    </Button>
                ))}
                <Button size="sm" variant="ghost" onClick={() => round(null, true)}>
                    Repetir round
                </Button>
            </div>

            <div className="flex flex-wrap gap-1">
                {encuentro.participantes.map((p) => (
                    <Button
                        key={`def-${p.id_inscripcion}`}
                        size="sm"
                        variant="ghost"
                        onClick={() => accion(EncuentroController.ganarPorDefault.url(encuentro.id_encuentro), p.id_inscripcion)}
                    >
                        Default {p.robot ?? '—'}
                    </Button>
                ))}
                {encuentro.participantes.map((p) => (
                    <Button
                        key={`dq-${p.id_inscripcion}`}
                        size="sm"
                        variant="destructive"
                        onClick={() => accion(EncuentroController.descalificar.url(encuentro.id_encuentro), p.id_inscripcion)}
                    >
                        Descalificar {p.robot ?? '—'}
                    </Button>
                ))}
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        Amonestar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Registrar amonestación</DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col gap-3">
                        <div className="flex gap-2">
                            {encuentro.participantes.map((p) => (
                                <Button
                                    key={`am-${p.id_inscripcion}`}
                                    size="sm"
                                    variant={amonestado === p.id_inscripcion ? 'default' : 'secondary'}
                                    onClick={() => setAmonestado(p.id_inscripcion)}
                                >
                                    {p.robot ?? '—'}
                                </Button>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`motivo-${encuentro.id_encuentro}`}>Motivo</Label>
                            <Input
                                id={`motivo-${encuentro.id_encuentro}`}
                                value={motivo}
                                onChange={(e) => setMotivo(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            disabled={amonestado === null || motivo.trim() === ''}
                            onClick={() => amonestado !== null && amonestar(amonestado)}
                        >
                            Registrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {encuentro.amonestaciones.length > 0 && (
                <ul className="text-xs text-muted-foreground">
                    {encuentro.amonestaciones.map((a) => (
                        <li key={a.id_amonestacion}>⚠ {a.motivo}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
