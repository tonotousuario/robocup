import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InstitucionController from '@/actions/App/Http/Controllers/InstitucionController';
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
import type { Institucion } from '@/types';

const TIPOS = ['Pública', 'Privada', 'Independiente'] as const;

type Props = {
    institucion?: Institucion;
    trigger: React.ReactNode;
};

export default function InstitucionFormDialog({ institucion, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(institucion);
    const form = useForm({
        nombre: institucion?.nombre ?? '',
        tipo: institucion?.tipo ?? 'Pública',
        estado: institucion?.estado ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!isEdit) {
                    form.reset();
                }
            },
        };

        if (isEdit && institucion) {
            form.put(InstitucionController.update.url(institucion.id_institucion), options);
        } else {
            form.post(InstitucionController.store.url(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar institución' : 'Nueva institución'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />
                        <InputError message={form.errors.nombre} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo">Tipo</Label>
                        <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v as Institucion['tipo'])}>
                            <SelectTrigger id="tipo">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIPOS.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.tipo} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="estado">Estado</Label>
                        <Input id="estado" value={form.data.estado} onChange={(e) => form.setData('estado', e.target.value)} />
                        <InputError message={form.errors.estado} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Guardar' : 'Crear'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
