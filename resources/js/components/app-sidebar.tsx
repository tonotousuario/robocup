import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Bot, Building2, ClipboardCheck, FolderGit2, LayoutGrid, Receipt, Swords, Timer, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import inspecciones from '@/routes/inspecciones';
import tiempos from '@/routes/tiempos';
import inscripciones from '@/routes/inscripciones';
import instituciones from '@/routes/instituciones';
import robots from '@/routes/robots';
import usuarios from '@/routes/usuarios';
import { dashboard } from '@/routes';
import combate from '@/routes/combate';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Instituciones',
        href: instituciones.index(),
        icon: Building2,
        roles: ['Administrador'],
    },
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: Users,
        roles: ['Administrador'],
    },
    {
        title: 'Robots',
        href: robots.index(),
        icon: Bot,
        roles: ['Administrador', 'Piloto'],
    },
    {
        title: 'Inscripciones',
        href: inscripciones.index(),
        icon: Receipt,
        roles: ['Administrador', 'Piloto'],
    },
    {
        title: 'Inspección',
        href: inspecciones.index(),
        icon: ClipboardCheck,
        roles: ['Administrador', 'Juez', 'Piloto'],
    },
    {
        title: 'Tiempos',
        href: tiempos.index(),
        icon: Timer,
        roles: ['Administrador', 'Juez', 'Coach', 'Piloto'],
    },
    {
        title: 'Combate',
        href: combate.index(),
        icon: Swords,
        roles: ['Administrador', 'Juez', 'Coach', 'Piloto'],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const visibleNavItems = mainNavItems.filter(
        (item) => !item.roles || item.roles.includes(auth.user.rol),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
