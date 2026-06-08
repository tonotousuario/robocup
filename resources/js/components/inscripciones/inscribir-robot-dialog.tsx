import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InscripcionController from '@/actions/App/Http/Controllers/InscripcionController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RobotInscribible, TarifaVigente } from '@/types';

type Props = {
    robots: RobotInscribible[];
    tarifaVigente: TarifaVigente | null;
    trigger: React.ReactNode;
};

export default function InscribirRobotDialog({ robots, tarifaVigente, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ id_robot: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(InscripcionController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
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
                    <DialogTitle>Inscribir robot</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="id_robot">Robot</Label>
                        <Select value={form.data.id_robot} onValueChange={(v) => form.setData('id_robot', v)}>
                            <SelectTrigger id="id_robot">
                                <SelectValue placeholder="Selecciona un robot" />
                            </SelectTrigger>
                            <SelectContent>
                                {robots.map((r) => (
                                    <SelectItem key={r.id_robot} value={String(r.id_robot)}>
                                        {r.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_robot} />
                    </div>

                    <div className="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                        {tarifaVigente ? (
                            <p>
                                Tarifa vigente: <span className="font-medium">{tarifaVigente.descripcion}</span> — $
                                {tarifaVigente.monto}
                            </p>
                        ) : (
                            <p className="text-red-600 dark:text-red-400">No hay una tarifa vigente para hoy.</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || robots.length === 0 || tarifaVigente === null}>
                            Inscribir
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
