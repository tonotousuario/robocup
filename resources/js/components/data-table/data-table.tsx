import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronsUpDown, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';

export type Column<T> = {
    key: string;
    header: string;
    sortable?: boolean;
    render?: (row: T) => ReactNode;
    className?: string;
};

type DataTableProps<T> = {
    columns: Column<T>[];
    page: Paginated<T>;
    rowKey: (row: T) => string | number;
    sort?: string;
    dir?: string;
    onSort: (key: string) => void;
    toolbar?: ReactNode;
    empty: { icon: LucideIcon; title: string; description?: string };
};

export default function DataTable<T>({ columns, page, rowKey, sort, dir, onSort, toolbar, empty }: DataTableProps<T>) {
    return (
        <div className="flex flex-col gap-4">
            {toolbar}

            {page.data.length === 0 ? (
                <EmptyState icon={empty.icon} title={empty.title} description={empty.description} />
            ) : (
                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border">
                            <tr>
                                {columns.map((col) => (
                                    <th key={col.key} className={cn('p-3 font-medium', col.className)}>
                                        {col.sortable ? (
                                            <button
                                                type="button"
                                                onClick={() => onSort(col.key)}
                                                className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                                            >
                                                {col.header}
                                                {sort === col.key ? (
                                                    dir === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />
                                                ) : (
                                                    <ChevronsUpDown className="size-3.5 opacity-50" />
                                                )}
                                            </button>
                                        ) : (
                                            col.header
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((row) => (
                                <tr key={rowKey(row)} className="border-b border-sidebar-border/40 transition-colors last:border-0 hover:bg-muted/40">
                                    {columns.map((col) => (
                                        <td key={col.key} className={cn('p-3', col.className)}>
                                            {col.render ? col.render(row) : (row as Record<string, ReactNode>)[col.key]}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {page.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {page.from}–{page.to} de {page.total}
                    </span>
                    <div className="flex gap-1">
                        {page.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                className={cn(
                                    'rounded-md px-3 py-1.5',
                                    link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                                    !link.url && 'pointer-events-none opacity-50',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
