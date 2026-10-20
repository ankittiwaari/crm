import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/InvoiceController';
import { InvoiceStatusBadge } from '@/components/crm/invoice-status-badge';
import { Pagination } from '@/components/crm/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Invoice, Paginated } from '@/types';

type Filter = 'all' | 'draft' | 'unpaid' | 'overdue' | 'paid' | 'cancelled';

type Props = {
    invoices: Paginated<Invoice>;
    filters: { filter: Filter; search: string };
    summary: {
        outstanding: number;
        outstanding_count: number;
        overdue: number;
        overdue_count: number;
        invoiced_this_year: number;
        financial_year: string;
    };
};

const TABS: { value: Filter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Drafts' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'paid', label: 'Paid' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function InvoicesIndex({ invoices, filters, summary }: Props) {
    const [search, setSearch] = useState(filters.search);
    const firstRender = useRef(true);

    const apply = (changes: Partial<Props['filters']>) => {
        const next = { ...filters, search, ...changes };

        router.get(
            index.url(),
            {
                filter: next.filter === 'all' ? undefined : next.filter,
                search: next.search || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => apply({ search }), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const tiles = [
        {
            label: 'Outstanding',
            value: formatInr(summary.outstanding),
            note: `${summary.outstanding_count} unpaid ${summary.outstanding_count === 1 ? 'invoice' : 'invoices'}`,
            filter: 'unpaid' as Filter,
        },
        {
            label: 'Overdue',
            value: formatInr(summary.overdue),
            note: `${summary.overdue_count} past the due date`,
            filter: 'overdue' as Filter,
            alert: summary.overdue_count > 0,
        },
        {
            label: `Invoiced in FY ${summary.financial_year}`,
            value: formatInr(summary.invoiced_this_year),
            note: 'Issued invoices, including GST',
            filter: 'all' as Filter,
        },
    ];

    return (
        <>
            <Head title="Invoices" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Invoices
                    </h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New invoice
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {tiles.map((tile) => (
                        <button
                            key={tile.label}
                            type="button"
                            onClick={() => apply({ filter: tile.filter })}
                            className="space-y-1 rounded-xl border bg-card px-5 py-4 text-left shadow-xs transition-colors hover:border-primary/40"
                        >
                            <p className="text-sm text-muted-foreground">
                                {tile.label}
                            </p>
                            <p
                                className={cn(
                                    'text-2xl font-semibold tracking-tight',
                                    tile.alert &&
                                        'text-red-700 dark:text-red-400',
                                )}
                            >
                                {tile.value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {tile.note}
                            </p>
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div
                        role="tablist"
                        aria-label="Invoice status"
                        className="flex gap-1 overflow-x-auto rounded-lg bg-muted p-1"
                    >
                        {TABS.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                role="tab"
                                aria-selected={filters.filter === tab.value}
                                onClick={() => apply({ filter: tab.value })}
                                className={cn(
                                    'h-8 shrink-0 rounded-md px-3 text-sm transition-colors',
                                    filters.filter === tab.value
                                        ? 'bg-card font-medium text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            aria-label="Search invoices"
                            placeholder="Invoice number or client"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="bg-card pl-9"
                        />
                    </div>
                </div>

                {invoices.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card px-6 py-16 text-center">
                        <FileText className="size-8 text-muted-foreground" />
                        <p className="font-medium">No invoices here</p>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                New invoice
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">
                                            Number
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Client
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Date
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Due
                                        </th>
                                        <th className="px-3 py-3 text-right font-medium">
                                            Total
                                        </th>
                                        <th className="px-3 py-3 text-right font-medium">
                                            Balance
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoices.data.map((invoice) => (
                                        <tr
                                            key={invoice.id}
                                            onClick={() =>
                                                router.visit(
                                                    show.url(invoice.id),
                                                )
                                            }
                                            className="cursor-pointer transition-colors hover:bg-muted/50"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={show(invoice.id)}
                                                    prefetch
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                    className={cn(
                                                        'font-mono text-[13px]',
                                                        !invoice.number &&
                                                            'text-muted-foreground italic',
                                                    )}
                                                >
                                                    {invoice.display_number}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-3 font-medium">
                                                {invoice.client_name}
                                            </td>
                                            <td className="px-3 py-3 text-muted-foreground">
                                                {formatDate(invoice.issue_date)}
                                            </td>
                                            <td className="px-3 py-3 text-muted-foreground">
                                                {formatDate(invoice.due_date)}
                                            </td>
                                            <td className="px-3 py-3 text-right font-medium">
                                                {formatInr(invoice.total)}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {invoice.status === 'draft' ||
                                                invoice.status ===
                                                    'cancelled' ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    formatInr(
                                                        invoice.balance_due,
                                                    )
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <InvoiceStatusBadge
                                                    invoice={invoice}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t px-4 py-3">
                            <Pagination page={invoices} />
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
