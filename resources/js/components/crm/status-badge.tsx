import { cn } from '@/lib/utils';
import type { ClientStatusValue } from '@/types';

const STYLES: Record<ClientStatusValue, string> = {
    active: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    lead: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    inactive: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    churned: 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
};

export function ClientStatusBadge({
    status,
    label,
    className,
}: {
    status: ClientStatusValue;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                STYLES[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
