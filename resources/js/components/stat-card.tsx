type StatCardProps = {
    label: string;
    value: string | number;
};

export default function StatCard({ label, value }: StatCardProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-2 text-3xl font-semibold">{value}</p>
        </div>
    );
}
