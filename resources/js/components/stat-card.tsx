import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'accent' | 'success' | 'warning' | 'danger';

type StatCardProps = {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    tone?: Tone;
    hint?: string;
};

const toneClasses: Record<Tone, string> = {
    default: 'text-foreground',
    accent: 'text-primary',
    success: 'text-emerald-400',
    warning: 'text-amber-400',
    danger: 'text-destructive',
};

export default function StatCard({ label, value, icon: Icon, tone = 'default', hint }: StatCardProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-5 transition-colors hover:border-primary/50 dark:border-sidebar-border">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{label}</p>
                {Icon ? <Icon className={cn('size-5', toneClasses[tone])} /> : null}
            </div>
            <p className={cn('mt-2 text-3xl font-bold', toneClasses[tone])}>{value}</p>
            {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
        </div>
    );
}
