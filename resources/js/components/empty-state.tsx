import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EmptyStateProps = {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: { label: string; href: string };
};

export default function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center">
            <Icon className="size-10 text-muted-foreground" />
            <p className="font-display text-lg font-semibold">{title}</p>
            {description ? <p className="max-w-sm text-sm text-muted-foreground">{description}</p> : null}
            {action ? (
                <Button asChild size="sm" className="mt-2">
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            ) : null}
        </div>
    );
}
