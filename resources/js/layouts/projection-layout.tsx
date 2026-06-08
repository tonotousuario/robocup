import AppLogoIcon from '@/components/app-logo-icon';

type ProjectionLayoutProps = {
    children: React.ReactNode;
};

export default function ProjectionLayout({ children }: ProjectionLayoutProps) {
    return (
        <div className="dark flex min-h-screen flex-col bg-background text-foreground">
            <header className="flex items-center gap-3 border-b border-sidebar-border/70 px-8 py-4">
                <div className="flex aspect-square size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-6" />
                </div>
                <span className="font-display text-2xl font-bold tracking-wide">RoboLeague</span>
            </header>
            <main className="flex-1 overflow-auto p-8">{children}</main>
        </div>
    );
}
