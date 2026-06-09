import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

type QuickActionCardProps = {
    icon: LucideIcon;
    label: string;
    href: string;
};

export default function QuickActionCard({ icon: Icon, label, href }: QuickActionCardProps) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:border-primary hover:bg-primary/5"
        >
            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                <Icon className="size-5" />
            </span>
            <span className="font-medium">{label}</span>
        </Link>
    );
}
