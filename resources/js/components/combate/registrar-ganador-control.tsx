import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import { Button } from '@/components/ui/button';
import type { EncuentroBracket } from '@/types';

type Props = {
    encuentro: EncuentroBracket;
};

export default function RegistrarGanadorControl({ encuentro }: Props) {
    const marcar = (idInscripcion: number) => {
        router.patch(
            EncuentroController.registrarGanador.url(encuentro.id_encuentro),
            { id_inscripcion: idInscripcion },
            {
                preserveScroll: true,
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
        <div className="mt-2 flex flex-col gap-1">
            {encuentro.participantes.map((p) => (
                <Button key={p.id_inscripcion} size="sm" variant="secondary" onClick={() => marcar(p.id_inscripcion)}>
                    Gana {p.robot ?? '—'}
                </Button>
            ))}
        </div>
    );
}
