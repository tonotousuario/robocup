import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RobotController from '@/actions/App/Http/Controllers/RobotController';
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
import type { Auth, CategoriaOpcion, InstitucionOpcion, PilotoOpcion, RobotRow } from '@/types';

const SIN_INSTITUCION = 'none';

type Props = {
    robot?: RobotRow;
    categorias: CategoriaOpcion[];
    instituciones: InstitucionOpcion[];
    pilotos: PilotoOpcion[];
    trigger: React.ReactNode;
};

export default function RobotFormDialog({ robot, categorias, instituciones, pilotos, trigger }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.user.rol === 'Administrador';
    const [open, setOpen] = useState(false);
    const isEdit = Boolean(robot);

    const form = useForm({
        nombre: robot?.nombre ?? '',
        id_categoria: '',
        id_institucion: SIN_INSTITUCION,
        id_piloto: robot ? String(robot.id_piloto) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form
            .transform((data) => ({
                ...data,
                id_institucion: data.id_institucion === SIN_INSTITUCION ? null : data.id_institucion,
                id_piloto: isAdmin ? data.id_piloto : undefined,
            }))
            .submit(
                isEdit && robot ? 'put' : 'post',
                isEdit && robot ? RobotController.update.url(robot.id_robot) : RobotController.store.url(),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setOpen(false);
                        if (!isEdit) {
                            form.reset();
                        }
                    },
                    onError: (errors) => {
                        const message = Object.values(errors)[0];
                        if (message) {
                            toast.error(message);
                        }
                    },
                },
            );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar robot' : 'Nuevo robot'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />
                        <InputError message={form.errors.nombre} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_categoria">Categoría</Label>
                        <Select value={form.data.id_categoria} onValueChange={(v) => form.setData('id_categoria', v)}>
                            <SelectTrigger id="id_categoria">
                                <SelectValue placeholder="Selecciona una categoría" />
                            </SelectTrigger>
                            <SelectContent>
                                {categorias.map((c) => (
                                    <SelectItem key={c.id_categoria} value={String(c.id_categoria)}>
                                        {c.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_categoria} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_institucion">Institución</Label>
                        <Select value={form.data.id_institucion} onValueChange={(v) => form.setData('id_institucion', v)}>
                            <SelectTrigger id="id_institucion">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SIN_INSTITUCION}>Sin institución</SelectItem>
                                {instituciones.map((i) => (
                                    <SelectItem key={i.id_institucion} value={String(i.id_institucion)}>
                                        {i.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.id_institucion} />
                    </div>

                    {isAdmin && (
                        <div className="grid gap-2">
                            <Label htmlFor="id_piloto">Piloto</Label>
                            <Select value={form.data.id_piloto} onValueChange={(v) => form.setData('id_piloto', v)}>
                                <SelectTrigger id="id_piloto">
                                    <SelectValue placeholder="Selecciona un piloto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {pilotos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.id_piloto} />
                        </div>
                    )}

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
