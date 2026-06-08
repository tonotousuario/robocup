import { Head, Link } from '@inertiajs/react';
import proyeccion from '@/routes/proyeccion';
import type { CategoriaCombateOpcion } from '@/types';

type PageProps = {
    categoriasCombate: CategoriaCombateOpcion[];
};

export default function ProyeccionIndex({ categoriasCombate }: PageProps) {
    return (
        <>
            <Head title="Proyección" />
            <div className="mx-auto flex max-w-2xl flex-col gap-6">
                <h1 className="font-display text-4xl font-bold">Proyección de competición</h1>
                <p className="text-xl text-muted-foreground">Elige la categoría de combate a proyectar:</p>
                <div className="flex flex-col gap-3">
                    {categoriasCombate.length === 0 ? (
                        <p className="text-muted-foreground">No hay categorías de combate.</p>
                    ) : (
                        categoriasCombate.map((c) => (
                            <Link
                                key={c.id_categoria}
                                href={proyeccion.combate(c.id_categoria).url}
                                className="rounded-xl border-2 border-sidebar-border/70 bg-card p-5 font-display text-2xl transition-colors hover:border-primary"
                            >
                                {c.nombre}
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}
