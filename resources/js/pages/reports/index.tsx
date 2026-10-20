import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Download, Table2 } from 'lucide-react';
import { useState } from 'react';
import { show as showInvoice } from '@/actions/App/Http/Controllers/InvoiceController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { todayIso } from '@/lib/billing';
import { formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import { exportMethod as exportRoute, index } from '@/routes/reports';
import type { Option } from '@/types';

type Month = {
    month: string;
    label: string;
    revenue: number;
    collected: number;
    expenses: number;
};

type Props = {
    year: string;
    yearStart: string;
    yearEnd: string;
    years: string[];
    totals: {
        revenue: number;
        gst: number;
        invoiced: number;
        collected: number;
        tds: number;
        expenses: number;
        input_gst: number;
        profit: number;
        invoice_count: number;
    };
    months: Month[];
    byProduct: { product: string; revenue: number; share: number }[];
    tdsByClient: { client_name: string; tds: number; payments: number }[];
    ageing: {
        total: number;
        buckets: { label: string; amount: number; count: number }[];
        over_msme_limit: {
            id: number;
            number: string;
            client_name: string;
            balance_due: number;
            days: number;
        }[];
    };
    pricing: {
        product: string;
        unit_label: string | null;
        subscriptions: number;
        promotional: number;
        per_unit: number;
        annual_total: number;
        annual_average: number | null;
        annual_min: number | null;
        annual_max: number | null;
        units_recorded: number;
        per_unit_yearly: number | null;
    }[];
    exportTypes: Option[];
};

const AGEING_COLOURS = ['#c7d2fe', '#818cf8', '#4f46e5', '#312e81'];

function compactInr(paise: number): string {
    const rupees = paise / 100;

    if (rupees >= 1_00_00_000) {
        return `₹${(rupees / 1_00_00_000).toFixed(1)}Cr`;
    }

    if (rupees >= 1_00_000) {
        return `₹${(rupees / 1_00_000).toFixed(1)}L`;
    }

    if (rupees >= 1_000) {
        return `₹${(rupees / 1_000).toFixed(0)}k`;
    }

    return `₹${rupees.toFixed(0)}`;
}

function Card({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn('rounded-xl border bg-card p-5 shadow-xs', className)}
        >
            <h2 className="text-base font-semibold">{title}</h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

function MonthlyChart({ months }: { months: Month[] }) {
    const [showTable, setShowTable] = useState(false);
    const max = Math.max(1, ...months.flatMap((m) => [m.revenue, m.expenses]));
    const series = [
        { key: 'revenue' as const, label: 'Revenue', colour: '#4f46e5' },
        { key: 'expenses' as const, label: 'Expenses', colour: '#ea580c' },
    ];

    return (
        <Card
            title="Revenue and expenses by month"
            description="Revenue is the taxable value of invoices issued; expenses exclude claimable GST."
        >
            <div className="mb-3 flex flex-wrap items-center gap-4 text-sm">
                {series.map((s) => (
                    <span key={s.key} className="flex items-center gap-1.5">
                        <span
                            className="size-2.5 rounded-sm"
                            style={{ background: s.colour }}
                        />
                        {s.label}
                    </span>
                ))}
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto"
                    onClick={() => setShowTable(!showTable)}
                >
                    <Table2 />
                    {showTable ? 'Show chart' : 'Show table'}
                </Button>
            </div>
            {showTable ? (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr className="text-left text-xs text-muted-foreground">
                                <th className="py-2 font-medium">Month</th>
                                <th className="py-2 text-right font-medium">
                                    Revenue
                                </th>
                                <th className="py-2 text-right font-medium">
                                    Collected
                                </th>
                                <th className="py-2 text-right font-medium">
                                    Expenses
                                </th>
                                <th className="py-2 text-right font-medium">
                                    Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {months.map((m) => (
                                <tr key={m.month}>
                                    <td className="py-2">{m.label}</td>
                                    <td className="py-2 text-right">
                                        {formatInr(m.revenue)}
                                    </td>
                                    <td className="py-2 text-right">
                                        {formatInr(m.collected)}
                                    </td>
                                    <td className="py-2 text-right">
                                        {formatInr(m.expenses)}
                                    </td>
                                    <td className="py-2 text-right font-medium">
                                        {formatInr(m.revenue - m.expenses)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div
                    role="img"
                    aria-label="Monthly revenue and expenses. Use Show table for exact figures."
                    className="relative"
                >
                    <div className="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
                        {[1, 0.5, 0].map((fraction) => (
                            <div
                                key={fraction}
                                className="flex items-center gap-2 border-t border-dashed border-border/70"
                            >
                                <span className="-mt-2.5 bg-card pr-1 text-[10px] text-muted-foreground">
                                    {fraction > 0
                                        ? compactInr(max * fraction)
                                        : ''}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="flex h-56 items-end gap-1 pl-10 sm:gap-2">
                        {months.map((m) => (
                            <div
                                key={m.month}
                                className="flex h-full min-w-0 flex-1 flex-col justify-end"
                            >
                                <div className="flex flex-1 items-end justify-center gap-0.5">
                                    {series.map((s) => (
                                        <div
                                            key={s.key}
                                            title={`${m.label} ${s.label}: ${formatInr(m[s.key])}`}
                                            className="w-full max-w-4 rounded-t transition-opacity hover:opacity-80"
                                            style={{
                                                height: `${(m[s.key] / max) * 100}%`,
                                                minHeight: m[s.key] > 0 ? 2 : 0,
                                                background: s.colour,
                                            }}
                                        />
                                    ))}
                                </div>
                                <span className="mt-1.5 h-4 text-center text-[11px] text-muted-foreground">
                                    {m.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </Card>
    );
}

function Exports({
    types,
    yearStart,
    yearEnd,
}: {
    types: Option[];
    yearStart: string;
    yearEnd: string;
}) {
    const [from, setFrom] = useState(yearStart);
    const [to, setTo] = useState(yearEnd < todayIso() ? yearEnd : todayIso());

    const now = new Date();
    const iso = (date: Date) =>
        new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
            .toISOString()
            .slice(0, 10);
    const ranges = [
        {
            label: 'This month',
            from: iso(new Date(now.getFullYear(), now.getMonth(), 1)),
            to: iso(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
        },
        {
            label: 'Last month',
            from: iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)),
            to: iso(new Date(now.getFullYear(), now.getMonth(), 0)),
        },
        { label: 'Selected FY', from: yearStart, to: yearEnd },
    ];

    return (
        <Card
            title="Downloads for your CA"
            description="CSV files that open in Excel. GSTR-1 files follow the GST offline tool sections; check them against the current format before uploading."
        >
            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="export_from">From</Label>
                    <Input
                        id="export_from"
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="w-40"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="export_to">To</Label>
                    <Input
                        id="export_to"
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="w-40"
                    />
                </div>
                <div className="flex flex-wrap gap-1.5 pb-1.5">
                    {ranges.map((range) => (
                        <button
                            key={range.label}
                            type="button"
                            onClick={() => {
                                setFrom(range.from);
                                setTo(range.to);
                            }}
                            className={cn(
                                'h-7 rounded-full border px-3 text-xs transition-colors',
                                from === range.from && to === range.to
                                    ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {range.label}
                        </button>
                    ))}
                </div>
            </div>
            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {types.map((type) => (
                    <Button
                        key={type.value}
                        variant="outline"
                        className="justify-start"
                        asChild
                    >
                        <a
                            href={exportRoute.url({
                                query: { type: type.value, from, to },
                            })}
                        >
                            <Download />
                            {type.label}
                        </a>
                    </Button>
                ))}
            </div>
        </Card>
    );
}

export default function ReportsIndex(props: Props) {
    const { totals, ageing, byProduct, pricing, tdsByClient } = props;
    const tiles = [
        {
            label: 'Revenue',
            value: totals.revenue,
            note: `${totals.invoice_count} invoices, before GST`,
        },
        {
            label: 'GST charged',
            value: totals.gst,
            note: `Input GST on expenses ${formatInr(totals.input_gst)}`,
        },
        {
            label: 'Collected',
            value: totals.collected,
            note: `Including ${formatInr(totals.tds)} TDS`,
        },
        {
            label: 'Expenses',
            value: totals.expenses,
            note: 'After claimable GST',
        },
        {
            label: 'Profit',
            value: totals.profit,
            note: 'Revenue minus expenses, before tax',
        },
    ];
    const maxProduct = Math.max(1, ...byProduct.map((row) => row.revenue));

    return (
        <>
            <Head title="Reports" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Reports
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Financial year {props.year} (April to March).
                        </p>
                    </div>
                    <Select
                        value={props.year}
                        onValueChange={(year) =>
                            router.get(
                                index.url(),
                                { year },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger
                            aria-label="Financial year"
                            className="w-40 bg-card"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {props.years.map((year) => (
                                <SelectItem key={year} value={year}>
                                    FY {year}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {tiles.map((tile) => (
                        <div
                            key={tile.label}
                            className="space-y-1 rounded-xl border bg-card px-4 py-4 shadow-xs"
                        >
                            <p className="text-sm text-muted-foreground">
                                {tile.label}
                            </p>
                            <p
                                className={cn(
                                    'text-xl font-semibold tracking-tight',
                                    tile.value < 0 &&
                                        'text-red-700 dark:text-red-400',
                                )}
                            >
                                {formatInr(tile.value)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {tile.note}
                            </p>
                        </div>
                    ))}
                </div>

                <MonthlyChart months={props.months} />

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card
                        title="Revenue by product"
                        description="Taxable value invoiced this year."
                    >
                        {byProduct.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No invoices issued in this year yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {byProduct.map((row) => (
                                    <li key={row.product} className="space-y-1">
                                        <div className="flex justify-between gap-3 text-sm">
                                            <span>{row.product}</span>
                                            <span className="font-medium">
                                                {formatInr(row.revenue)}{' '}
                                                <span className="font-normal text-muted-foreground">
                                                    {row.share}%
                                                </span>
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-muted">
                                            <div
                                                className="h-2 rounded-full bg-primary"
                                                style={{
                                                    width: `${(row.revenue / maxProduct) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card
                        title="Money owed to you"
                        description="Unpaid invoices by age since the invoice date, as of today."
                    >
                        <p className="text-2xl font-semibold tracking-tight">
                            {formatInr(ageing.total)}
                        </p>
                        {ageing.total > 0 && (
                            <>
                                <div
                                    role="img"
                                    aria-label={ageing.buckets
                                        .map(
                                            (bucket) =>
                                                `${bucket.label}: ${formatInr(bucket.amount)}`,
                                        )
                                        .join('; ')}
                                    className="mt-3 flex h-3 gap-0.5"
                                >
                                    {ageing.buckets.map((bucket, position) =>
                                        bucket.amount > 0 ? (
                                            <div
                                                key={bucket.label}
                                                title={`${bucket.label}: ${formatInr(bucket.amount)}`}
                                                className="first:rounded-l last:rounded-r"
                                                style={{
                                                    flexGrow: bucket.amount,
                                                    background:
                                                        AGEING_COLOURS[
                                                            position
                                                        ],
                                                }}
                                            />
                                        ) : null,
                                    )}
                                </div>
                                <ul className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    {ageing.buckets.map((bucket, position) => (
                                        <li
                                            key={bucket.label}
                                            className="flex items-center gap-2"
                                        >
                                            <span
                                                className="size-2.5 shrink-0 rounded-sm"
                                                style={{
                                                    background:
                                                        AGEING_COLOURS[
                                                            position
                                                        ],
                                                }}
                                            />
                                            <span className="flex-1 text-muted-foreground">
                                                {bucket.label}
                                            </span>
                                            <span className="font-medium">
                                                {formatInr(bucket.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}
                        {ageing.over_msme_limit.length > 0 && (
                            <div className="mt-4 space-y-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                <p className="flex items-center gap-2 font-medium">
                                    <AlertTriangle className="size-4" />
                                    Past the MSME 45-day limit
                                </p>
                                <ul className="space-y-1">
                                    {ageing.over_msme_limit.map((invoice) => (
                                        <li key={invoice.id}>
                                            <Link
                                                href={showInvoice(invoice.id)}
                                                className="underline-offset-2 hover:underline"
                                            >
                                                <span className="font-mono">
                                                    {invoice.number}
                                                </span>{' '}
                                                · {invoice.client_name} ·{' '}
                                                {formatInr(invoice.balance_due)}{' '}
                                                · {invoice.days} days
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </Card>
                </div>

                <Card
                    title="Pricing insight"
                    description="Across active subscriptions today. “Per unit” uses the students (or units) you have recorded, to suggest a standard per-unit price."
                >
                    {pricing.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Add subscriptions to see pricing patterns.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Product
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Clients
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            On promo
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Average / yr
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Range / yr
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Effective per unit / yr
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {pricing.map((row) => (
                                        <tr key={row.product}>
                                            <td className="py-2.5 font-medium">
                                                {row.product}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {row.subscriptions}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {row.promotional}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {row.annual_average === null
                                                    ? '—'
                                                    : formatInr(
                                                          row.annual_average,
                                                      )}
                                            </td>
                                            <td className="py-2.5 text-right text-muted-foreground">
                                                {row.annual_min === null ||
                                                row.annual_max === null
                                                    ? '—'
                                                    : `${formatInr(row.annual_min)}–${formatInr(row.annual_max)}`}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {row.per_unit_yearly ===
                                                null ? (
                                                    <span className="text-muted-foreground">
                                                        Record unit counts
                                                    </span>
                                                ) : (
                                                    <>
                                                        {formatInr(
                                                            row.per_unit_yearly,
                                                        )}{' '}
                                                        <span className="text-muted-foreground">
                                                            {row.unit_label ??
                                                                'per unit'}{' '}
                                                            ·{' '}
                                                            {row.units_recorded}{' '}
                                                            units
                                                        </span>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                {tdsByClient.length > 0 && (
                    <Card
                        title="TDS deducted by clients"
                        description="Match these against Form 26AS when filing your income tax return."
                    >
                        <ul className="divide-y text-sm">
                            {tdsByClient.map((row) => (
                                <li
                                    key={row.client_name}
                                    className="flex justify-between gap-3 py-2"
                                >
                                    <span>
                                        {row.client_name}{' '}
                                        <span className="text-muted-foreground">
                                            · {row.payments}{' '}
                                            {row.payments === 1
                                                ? 'payment'
                                                : 'payments'}
                                        </span>
                                    </span>
                                    <span className="font-medium">
                                        {formatInr(row.tds)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Exports
                    types={props.exportTypes}
                    yearStart={props.yearStart}
                    yearEnd={props.yearEnd}
                />
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
