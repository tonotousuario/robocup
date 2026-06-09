import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ProjectionBracket from '@/components/proyeccion/projection-bracket';
import ProjectionPodium from '@/components/proyeccion/projection-podium';
import ProjectionStandings from '@/components/proyeccion/projection-standings';
import { Button } from '@/components/ui/button';
import { useCuentaRegresiva } from '@/hooks/use-cuenta-regresiva';
import proyeccion from '@/routes/proyeccion';
import type { CategoriaCombateOpcion, EncuentroBracket, Podio, ProyeccionEnVivo, ProyeccionPosicion, ReparacionActiva } from '@/types';

type Vista = 'bracket' | 'marcador' | 'rotar';

type PageProps = {
    categoria: CategoriaCombateOpcion;
    encuentros: EncuentroBracket[];
    enVivo: ProyeccionEnVivo | null;
    posiciones: ProyeccionPosicion[];
    reparacionesActivas: ReparacionActiva[];
    podio: Podio | null;
};

const POLL_MS = 5000;
const ROTAR_MS = 12000;

function vistaFromUrl(): Vista {
    if (typeof window === 'undefined') {
        return 'bracket';
    }
    const v = new URLSearchParams(window.location.search).get('vista');
    return v === 'marcador' || v === 'rotar' ? v : 'bracket';
}

export default function ProyeccionCombate() {
    const { categoria, encuentros, enVivo, posiciones, reparacionesActivas, podio } = usePage<PageProps>().props;
    const [vista, setVista] = useState<Vista>(vistaFromUrl);
    const [rotarMostrandoBracket, setRotarMostrandoBracket] = useState(true);

    // Auto-refresh de datos (polling).
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['encuentros', 'enVivo', 'posiciones', 'reparacionesActivas', 'podio'] });
        }, POLL_MS);
        return () => clearInterval(id);
    }, []);

    // Rotación de vista cuando vista === 'rotar'.
    useEffect(() => {
        if (vista !== 'rotar') {
            return;
        }
        const id = setInterval(() => setRotarMostrandoBracket((prev) => !prev), ROTAR_MS);
        return () => clearInterval(id);
    }, [vista]);

    const cambiarVista = (next: Vista) => {
        setVista(next);
        router.get(proyeccion.combate(categoria.id_categoria).url, { vista: next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const botones: { vista: Vista; label: string }[] = [
        { vista: 'bracket', label: 'Bracket' },
        { vista: 'marcador', label: 'Marcador' },
        { vista: 'rotar', label: 'Rotar' },
    ];

    return (
        <>
            <Head title={`Proyección · ${categoria.nombre}`} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="font-display text-4xl font-bold">{categoria.nombre}</h1>
                <div className="flex gap-2">
                    {botones.map((b) => (
                        <Button
                            key={b.vista}
                            variant={vista === b.vista ? 'default' : 'secondary'}
                            onClick={() => cambiarVista(b.vista)}
                        >
                            {b.label}
                        </Button>
                    ))}
                </div>
            </div>

            {reparacionesActivas.length > 0 && (
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    {reparacionesActivas.map((r) => (
                        <FranjaReparacion
                            key={r.reparacion_iniciada_en + (r.robot ?? '')}
                            robot={r.robot}
                            iniciadaEn={r.reparacion_iniciada_en}
                            consumidos={r.reparacion_segundos_consumidos}
                        />
                    ))}
                </div>
            )}

            {podio ? (
                <ProjectionPodium podio={podio} />
            ) : (
                <>
                    {vista === 'marcador' && enVivo && (
                        <div className="mb-8 rounded-2xl border-2 border-primary bg-card p-8 text-center">
                            <p className="font-display text-xl uppercase tracking-widest text-muted-foreground">{enVivo.ronda} · En vivo</p>
                            <p className="mt-3 font-display text-5xl font-bold">
                                {(enVivo.robots[0] ?? '—')} <span className="text-primary">vs</span> {(enVivo.robots[1] ?? '—')}
                            </p>
                            {enVivo.marcador.length === 2 && (
                                <p className="mt-4 text-center font-display text-4xl">
                                    {enVivo.marcador[0].robot ?? '—'}{' '}
                                    <span className="text-primary">{enVivo.marcador[0].rounds}</span>
                                    {' – '}
                                    <span className="text-primary">{enVivo.marcador[1].rounds}</span>{' '}
                                    {enVivo.marcador[1].robot ?? '—'}
                                </p>
                            )}
                            {enVivo.amonestaciones.length > 0 && (
                                <ul className="mt-4 flex flex-col items-center gap-1 text-xl text-amber-300">
                                    {enVivo.amonestaciones.map((a, i) => (
                                        <li key={`${i}-${a.robot}`}>⚠ {a.robot ?? '—'}: {a.motivo}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {vista === 'rotar' && !rotarMostrandoBracket ? (
                        <ProjectionStandings posiciones={posiciones} />
                    ) : (
                        <ProjectionBracket encuentros={encuentros} />
                    )}
                </>
            )}
        </>
    );
}

const REPARACION_SEGUNDOS = 300;

function FranjaReparacion({ robot, iniciadaEn, consumidos }: { robot: string | null; iniciadaEn: string; consumidos: number }) {
    const finIso = new Date(Date.parse(iniciadaEn) + (REPARACION_SEGUNDOS - consumidos) * 1000).toISOString();
    const { segundosRestantes, mmss } = useCuentaRegresiva(finIso);

    if (segundosRestantes <= 0) {
        return null;
    }

    return (
        <span className="rounded-lg bg-amber-500/20 px-4 py-2 font-display text-2xl text-amber-300">
            🔧 {robot ?? '—'} · {mmss}
        </span>
    );
}
