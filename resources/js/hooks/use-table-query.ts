import { router } from '@inertiajs/react';
import { useCallback, useRef } from 'react';

type Params = Record<string, string | number | undefined>;

/**
 * Centraliza visitas parciales para tablas: preserva los demás query params
 * al cambiar uno; `q` se aplica con debounce.
 */
export function useTableQuery(routeUrl: string, only: string[], current: Params) {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const visit = useCallback(
        (next: Params) => {
            const merged: Params = { ...current, ...next };
            Object.keys(merged).forEach((k) => {
                if (merged[k] === undefined || merged[k] === '' || merged[k] === 'todos') {
                    delete merged[k];
                }
            });
            router.get(routeUrl, merged, { preserveState: true, preserveScroll: true, replace: true, only });
        },
        [routeUrl, only, current],
    );

    const setFiltro = useCallback((key: string, value: string | number | undefined) => visit({ [key]: value, page: undefined }), [visit]);

    const setBusqueda = useCallback(
        (value: string) => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
            timer.current = setTimeout(() => visit({ q: value, page: undefined }), 300);
        },
        [visit],
    );

    const setOrden = useCallback(
        (sort: string, currentSort?: string, currentDir?: string) => {
            const dir = currentSort === sort && currentDir === 'asc' ? 'desc' : 'asc';
            visit({ sort, dir, page: undefined });
        },
        [visit],
    );

    return { setFiltro, setBusqueda, setOrden };
}
