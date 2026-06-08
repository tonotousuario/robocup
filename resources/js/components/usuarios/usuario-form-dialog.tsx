import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
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
import type { UsuarioRow } from '@/types';

const ROLES = ['Administrador', 'Juez', 'Coach', 'Piloto'] as const;

type Props = {
    usuario?: UsuarioRow;
    trigger: React.ReactNode;
};

export default function UsuarioFormDialog({ usuario, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(usuario);
    const form = useForm({
        name: usuario?.name ?? '',
        apellidos: usuario?.apellidos ?? '',
        email: usuario?.email ?? '',
        telefono: usuario?.telefono ?? '',
        rol: usuario?.rol ?? 'Piloto',
        password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('password');
                if (!isEdit) {
                    form.reset();
                }
            },
        };

        if (isEdit && usuario) {
            form.put(UsuarioController.update.url(usuario.id), options);
        } else {
            form.post(UsuarioController.store.url(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="apellidos">Apellidos</Label>
                        <Input id="apellidos" value={form.data.apellidos} onChange={(e) => form.setData('apellidos', e.target.value)} />
                        <InputError message={form.errors.apellidos} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo</Label>
                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="telefono">Teléfono</Label>
                        <Input id="telefono" value={form.data.telefono ?? ''} onChange={(e) => form.setData('telefono', e.target.value)} />
                        <InputError message={form.errors.telefono} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rol">Rol</Label>
                        <Select value={form.data.rol} onValueChange={(v) => form.setData('rol', v as UsuarioRow['rol'])}>
                            <SelectTrigger id="rol">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ROLES.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {r}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.rol} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">{isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña'}</Label>
                        <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" />
                        <InputError message={form.errors.password} />
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
