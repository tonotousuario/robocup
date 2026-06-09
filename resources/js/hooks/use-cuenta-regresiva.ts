import { useEffect, useState } from 'react';

function calcularRestante(finIso: string): number {
    const fin = Date.parse(finIso);
    if (Number.isNaN(fin)) {
        return 0;
    }
    return Math.max(0, Math.floor((fin - Date.now()) / 1000));
}

export function formatearMmss(segundos: number): string {
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

/** Cuenta regresiva (en segundos) hasta `finIso`; tick cada segundo. */
export function useCuentaRegresiva(finIso: string): { segundosRestantes: number; mmss: string } {
    const [segundosRestantes, setSegundosRestantes] = useState(() => calcularRestante(finIso));

    useEffect(() => {
        setSegundosRestantes(calcularRestante(finIso));
        const id = setInterval(() => setSegundosRestantes(calcularRestante(finIso)), 1000);
        return () => clearInterval(id);
    }, [finIso]);

    return { segundosRestantes, mmss: formatearMmss(segundosRestantes) };
}
