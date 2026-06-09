import type { EncuentroBracket } from '@/types';

const ORDEN_RONDAS = ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'];

type Props = {
    encuentros: EncuentroBracket[];
};

export default function ProjectionBracket({ encuentros }: Props) {
    const rondas = ORDEN_RONDAS.filter((r) => encuentros.some((e) => e.ronda === r));

    if (encuentros.length === 0) {
        return <p className="text-2xl text-muted-foreground">Bracket no generado.</p>;
    }

    return (
        <div className="flex gap-10 overflow-x-auto">
            {rondas.map((ronda) => (
                <div key={ronda} className="flex min-w-72 flex-col justify-center gap-6">
                    <h2 className="font-display text-xl font-bold uppercase tracking-widest text-muted-foreground">{ronda}</h2>
                    {encuentros
                        .filter((e) => e.ronda === ronda)
                        .map((encuentro) => (
                            <div
                                key={encuentro.id_encuentro}
                                className="rounded-xl border-2 border-sidebar-border/70 bg-card p-4"
                            >
                                {encuentro.participantes.length === 0 ? (
                                    <p className="text-2xl text-muted-foreground">Por definir</p>
                                ) : (
                                    encuentro.participantes.map((p) => (
                                        <p
                                            key={p.id_inscripcion}
                                            className={
                                                p.es_ganador
                                                    ? 'font-display text-3xl font-bold text-primary'
                                                    : 'font-display text-3xl text-foreground/80'
                                            }
                                        >
                                            {p.robot ?? '—'} {p.es_ganador ? '✓' : ''}
                                        </p>
                                    ))
                                )}
                            </div>
                        ))}
                </div>
            ))}
        </div>
    );
}
