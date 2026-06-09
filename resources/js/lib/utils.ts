import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function estadoBadgeVariant(estado: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (estado) {
        case 'Pagado':
        case 'Aprobado':
            return 'default';
        case 'Pendiente':
            return 'secondary';
        case 'Rechazado':
        case 'Descalificado':
            return 'destructive';
        default:
            return 'outline';
    }
}
