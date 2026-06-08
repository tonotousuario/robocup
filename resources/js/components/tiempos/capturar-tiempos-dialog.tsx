import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import TiempoController from '@/actions/App/Http/Controllers/TiempoController';
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
import type { CompetidorTiempo } from '@/types';

type Fila = { tiempo_logrado: string; penalizacion_segundos: string };

type Props = {
    competidor: CompetidorTiempo;
    trigger: React.ReactNode;
};

const VUELTAS = [1, 2, 3] as const;

function filaInicial(competidor: CompetidorTiempo, vuelta: number): Fila {
    const intento = competidor.intentos.find((i) => i.numero_vuelta === vuelta);
    return {
        tiempo_logrado: intento?.tiempo_logrado ?? '',
        penalizacion_segundos: intento?.penalizacion_segundos ?? '',
    };
}

export default function CapturarTiemposDialog({ competidor, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ id_inscripcion: number; filas: Record<number, Fila> }>({
        id_inscripcion: competidor.id_inscripcion,
        filas: {
            1: filaInicial(competidor, 1),
            2: filaInicial(competidor, 2),
            3: filaInicial(competidor, 3),
        },
    });

    const setFila = (vuelta: number, campo: keyof Fila, valor: string) => {
        form.setData('filas', {
            ...form.data.filas,
            [vuelta]: { ...form.data.filas[vuelta], [campo]: valor },
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            id_inscripcion: data.id_inscripcion,
            intentos: VUELTAS.map((v) => ({
                numero_vuelta: v,
                tiempo_logrado: data.filas[v].tiempo_logrado === '' ? null : data.filas[v].tiempo_logrado,
                penalizacion_segundos: data.filas[v].penalizacion_segundos === '' ? 0 : data.filas[v].penalizacion_segundos,
            })),
        }));
        form.post(TiempoController.guardar.url(), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors) => {
                const message = Object.values(errors)[0];
                if (message) {
                    toast.error(message);
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Capturar tiempos · {competidor.robot}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {VUELTAS.map((v) => (
                        <div key={v} className="grid grid-cols-[auto_1fr_1fr] items-end gap-3">
                            <span className="pb-2 text-sm font-medium">Vuelta {v}</span>
                            <div className="grid gap-1">
                                <Label htmlFor={`tiempo-${v}`}>Tiempo (s)</Label>
                                <Input
                                    id={`tiempo-${v}`}
                                    type="number"
                                    step="0.001"
                                    value={form.data.filas[v].tiempo_logrado}
                                    onChange={(e) => setFila(v, 'tiempo_logrado', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor={`penal-${v}`}>Penalización (s)</Label>
                                <Input
                                    id={`penal-${v}`}
                                    type="number"
                                    step="0.001"
                                    value={form.data.filas[v].penalizacion_segundos}
                                    onChange={(e) => setFila(v, 'penalizacion_segundos', e.target.value)}
                                />
                            </div>
                        </div>
                    ))}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Guardar tiempos
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
