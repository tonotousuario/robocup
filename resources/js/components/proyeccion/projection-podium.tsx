import type { Podio } from '@/types';

type Props = {
    podio: Podio;
};

type Escalon = {
    lugar: number;
    medalla: string;
    robot: string | null;
    alturaClase: string;
    destacado: boolean;
};

export default function ProjectionPodium({ podio }: Props) {
    const escalones: Escalon[] = [
        { lugar: 2, medalla: '🥈', robot: podio.subcampeon, alturaClase: 'h-40', destacado: false },
        { lugar: 1, medalla: '🥇', robot: podio.campeon, alturaClase: 'h-56', destacado: true },
        { lugar: 3, medalla: '🥉', robot: podio.tercero, alturaClase: 'h-28', destacado: false },
    ].filter((e) => e.lugar !== 3 || e.robot !== null);

    return (
        <div className="flex flex-col items-center gap-10 py-10">
            <h2 className="font-display text-5xl font-bold uppercase tracking-widest">Podio</h2>
            <div className="flex items-end justify-center gap-6">
                {escalones.map((e) => (
                    <div key={e.lugar} className="flex w-64 flex-col items-center gap-3">
                        <span className="text-6xl">{e.medalla}</span>
                        <span
                            className={
                                e.destacado
                                    ? 'font-display text-4xl font-bold text-primary'
                                    : 'font-display text-3xl text-foreground/80'
                            }
                        >
                            {e.robot ?? '—'}
                        </span>
                        <div
                            className={`flex w-full items-start justify-center rounded-t-xl border-2 pt-3 ${e.alturaClase} ${
                                e.destacado ? 'border-primary bg-primary/15' : 'border-sidebar-border/70 bg-card'
                            }`}
                        >
                            <span className="font-display text-2xl text-muted-foreground">{e.lugar}°</span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
