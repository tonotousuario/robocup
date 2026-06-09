import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ProjectionBracket from '@/components/proyeccion/projection-bracket';
import ProjectionStandings from '@/components/proyeccion/projection-standings';
import { Button } from '@/components/ui/button';
import proyeccion from '@/routes/proyeccion';
import type { CategoriaCombateOpcion, EncuentroBracket, ProyeccionEnVivo, ProyeccionPosicion } from '@/types';

type Vista = 'bracket' | 'marcador' | 'rotar';

type PageProps = {
    categoria: CategoriaCombateOpcion;
    encuentros: EncuentroBracket[];
    enVivo: ProyeccionEnVivo | null;
    posiciones: ProyeccionPosicion[];
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
    const { categoria, encuentros, enVivo, posiciones } = usePage<PageProps>().props;
    const [vista, setVista] = useState<Vista>(vistaFromUrl);
    const [rotarMostrandoBracket, setRotarMostrandoBracket] = useState(true);

    // Auto-refresh de datos (polling).
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['encuentros', 'enVivo', 'posiciones'] });
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

            {vista === 'marcador' && enVivo && (
                <div className="mb-8 rounded-2xl border-2 border-primary bg-card p-8 text-center">
                    <p className="font-display text-xl uppercase tracking-widest text-muted-foreground">{enVivo.ronda} · En vivo</p>
                    <p className="mt-3 font-display text-5xl font-bold">
                        {(enVivo.robots[0] ?? '—')} <span className="text-primary">vs</span> {(enVivo.robots[1] ?? '—')}
                    </p>
                </div>
            )}

            {vista === 'rotar' && !rotarMostrandoBracket ? (
                <ProjectionStandings posiciones={posiciones} />
            ) : (
                <ProjectionBracket encuentros={encuentros} />
            )}
        </>
    );
}
