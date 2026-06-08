import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InspeccionController from '@/actions/App/Http/Controllers/InspeccionController';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { InspeccionListItem } from '@/types';

const ESTADOS = ['Aprobado', 'Rechazado', 'Descalificado'] as const;

type Props = {
    item: InspeccionListItem;
    trigger: React.ReactNode;
};

export default function InspeccionarDialog({ item, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        id_inscripcion: item.id_inscripcion,
        peso_medido_g: item.inspeccion ? String(item.inspeccion.peso_medido_g) : '',
        dimensiones_medidas: item.inspeccion?.dimensiones_medidas ?? '',
        estado_aprobacion: item.inspeccion?.estado_aprobacion ?? 'Aprobado',
        observaciones: item.inspeccion?.observaciones ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(InspeccionController.guardar.url(), {
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
                    <DialogTitle>Inspeccionar {item.robot}</DialogTitle>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    Límites: {item.peso_maximo_g ?? '—'} g · {item.dimensiones_maximas ?? '—'}
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="peso_medido_g">Peso medido (g)</Label>
                        <Input id="peso_medido_g" type="number" value={form.data.peso_medido_g} onChange={(e) => form.setData('peso_medido_g', e.target.value)} />
                        <InputError message={form.errors.peso_medido_g} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="dimensiones_medidas">Dimensiones medidas</Label>
                        <Input id="dimensiones_medidas" value={form.data.dimensiones_medidas} onChange={(e) => form.setData('dimensiones_medidas', e.target.value)} />
                        <InputError message={form.errors.dimensiones_medidas} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="estado_aprobacion">Veredicto</Label>
                        <Select value={form.data.estado_aprobacion} onValueChange={(v) => form.setData('estado_aprobacion', v)}>
                            <SelectTrigger id="estado_aprobacion">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ESTADOS.map((est) => (
                                    <SelectItem key={est} value={est}>
                                        {est}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.estado_aprobacion} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="observaciones">Observaciones</Label>
                        <Input id="observaciones" value={form.data.observaciones} onChange={(e) => form.setData('observaciones', e.target.value)} />
                        <InputError message={form.errors.observaciones} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Guardar inspección
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
