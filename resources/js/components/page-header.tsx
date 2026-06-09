import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export default function PageHeader({ title, description, action }: PageHeaderProps) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div>
                <h1 className="font-display text-2xl font-bold tracking-tight">{title}</h1>
                {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}
