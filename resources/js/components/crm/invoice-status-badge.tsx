import { cn } from '@/lib/utils';
import type { Invoice } from '@/types';

export function InvoiceStatusBadge({
    invoice,
    className,
}: {
    invoice: Pick<
        Invoice,
        'status' | 'status_label' | 'is_overdue' | 'days_overdue'
    >;
    className?: string;
}) {
    const [label, style] = invoice.is_overdue
        ? [
              `Overdue ${invoice.days_overdue}d`,
              'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
          ]
        : {
              draft: [
                  invoice.status_label,
                  'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
              ],
              issued: [
                  invoice.status_label,
                  'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
              ],
              partially_paid: [
                  invoice.status_label,
                  'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
              ],
              paid: [
                  invoice.status_label,
                  'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
              ],
              cancelled: [
                  invoice.status_label,
                  'bg-zinc-100 text-zinc-500 line-through dark:bg-zinc-800 dark:text-zinc-400',
              ],
          }[invoice.status];

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                style,
                className,
            )}
        >
            {label}
        </span>
    );
}
