import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Building2,
    FileText,
    LayoutGrid,
    Package,
    Receipt,
    Users,
} from 'lucide-react';
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
import { index as expenses } from '@/routes/expenses';
import { index as invoices } from '@/routes/invoices';
import { index as products } from '@/routes/products';
import { index as reminders } from '@/routes/reminders';
import { index as reports } from '@/routes/reports';
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
    {
        title: 'Expenses',
        href: expenses(),
        icon: Receipt,
    },
    {
        title: 'Reports',
        href: reports(),
        icon: BarChart3,
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
    const { remindersDue } = usePage().props;
    const items: NavItem[] = [
        mainNavItems[0],
        {
            title: 'Reminders',
            href: reminders(),
            icon: Bell,
            badge: remindersDue,
        },
        ...mainNavItems.slice(1),
    ];

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
                <NavMain items={items} />
                <NavMain items={companyNavItems} label="Company" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
