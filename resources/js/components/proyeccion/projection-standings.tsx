import type { ProyeccionPosicion } from '@/types';

type Props = {
    posiciones: ProyeccionPosicion[];
};

export default function ProjectionStandings({ posiciones }: Props) {
    if (posiciones.length === 0) {
        return <p className="text-2xl text-muted-foreground">Sin tiempos registrados.</p>;
    }

    return (
        <table className="w-full text-left">
            <thead>
                <tr className="border-b-2 border-sidebar-border/70">
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">#</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Robot</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Categoría</th>
                    <th className="p-4 font-display text-2xl uppercase tracking-widest text-muted-foreground">Mejor</th>
                </tr>
            </thead>
            <tbody>
                {posiciones.map((p, i) => (
                    <tr key={`${p.categoria}-${p.robot}-${i}`} className="border-b border-sidebar-border/40">
                        <td className="p-4 text-4xl font-bold text-primary">{i + 1}</td>
                        <td className="p-4 font-display text-3xl">{p.robot ?? '—'}</td>
                        <td className="p-4 text-2xl text-foreground/70">{p.categoria ?? '—'}</td>
                        <td className="p-4 text-3xl font-semibold">{p.mejor_tiempo ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
