import { Link } from '@inertiajs/react';
import { Building2, FileText, LayoutGrid, Package, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { dashboard } from '@/routes';
import { edit as businessProfile } from '@/routes/business-profile';
import { index as clients } from '@/routes/clients';
import { index as invoices } from '@/routes/invoices';
import { index as products } from '@/routes/products';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Today',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clients',
        href: clients(),
        icon: Users,
    },
    {
        title: 'Invoices',
        href: invoices(),
        icon: FileText,
    },
    {
        title: 'Products',
        href: products(),
        icon: Package,
    },
];

const companyNavItems: NavItem[] = [
    {
        title: 'Business Profile',
        href: businessProfile(),
        icon: Building2,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="sidebar">
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
                <NavMain items={mainNavItems} />
                <NavMain items={companyNavItems} label="Company" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
