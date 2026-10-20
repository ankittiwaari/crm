import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as reminders } from '@/routes/reminders';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { remindersDue } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Button
                asChild
                variant="ghost"
                size="icon"
                className="relative ml-auto"
            >
                <Link
                    href={reminders()}
                    aria-label={`Reminders, ${remindersDue} due`}
                >
                    <Bell />
                    {remindersDue > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-4 font-semibold text-white">
                            {remindersDue}
                        </span>
                    )}
                </Link>
            </Button>
        </header>
    );
}
