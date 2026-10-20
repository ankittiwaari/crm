import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    CheckCircle2,
    Circle,
    Package,
    RefreshCw,
    Sparkles,
    Users,
} from 'lucide-react';
import { show as showClient } from '@/actions/App/Http/Controllers/ClientController';
import { show as showInvoice } from '@/actions/App/Http/Controllers/InvoiceController';
import { ReminderRow } from '@/components/crm/reminder-row';
import { Button } from '@/components/ui/button';
import { formatDate, formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as businessProfile } from '@/routes/business-profile';
import { index as clients } from '@/routes/clients';
import { index as products } from '@/routes/products';
import { index as reminders } from '@/routes/reminders';
import type { BusinessProfileSummary, Reminder } from '@/types';

type UpcomingItem = {
    kind: 'renewal' | 'promo_ends' | 'in_grace';
    date: string;
    subscription_id: number;
    client_id: number;
    client_name: string;
    product_name: string;
    amount: number;
    billing_cycle_short: string;
    is_promotional: boolean;
};

type Props = {
    businessProfile: BusinessProfileSummary;
    setup: { has_products: boolean; has_clients: boolean };
    metrics: {
        annual_recurring: number;
        live_subscriptions: number;
        renewals_count: number;
        renewals_value: number;
        outstanding: number;
        outstanding_count: number;
        overdue_count: number;
    };
    overdue: {
        id: number;
        number: string;
        client_id: number;
        client_name: string;
        balance_due: number;
        due_date: string;
        days_overdue: number;
    }[];
    today: Reminder[];
    upcoming: UpcomingItem[];
    lookaheadDays: number;
};

function greeting(hour: number): string {
    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 17 ? 'Good afternoon' : 'Good evening';
}

function daysFromToday(date: string): number {
    return Math.round(
        (new Date(date).getTime() - new Date().setHours(0, 0, 0, 0)) /
            86_400_000,
    );
}

function whenLabel(date: string): string {
    const days = daysFromToday(date);

    if (days <= 0) {
        return 'today';
    }

    return days === 1 ? 'tomorrow' : `in ${days} days`;
}

export default function Dashboard({
    businessProfile: profile,
    setup,
    metrics,
    overdue,
    today,
    upcoming,
    lookaheadDays,
}: Props) {
    const { auth } = usePage().props;
    const now = new Date();
    const firstName = auth.user.name.split(' ')[0];
    const profileComplete = profile.missing.length === 0;

    const steps = [
        {
            title: 'Complete your business profile',
            description: profileComplete
                ? 'GSTIN, address and payment details are ready for invoices.'
                : `Still needed: ${profile.missing.join(', ')}.`,
            done: profileComplete,
            icon: Building2,
            href: businessProfile(),
        },
        {
            title: 'Add your products',
            description: 'School ERP, Pharmacy Management and the rest.',
            done: setup.has_products,
            icon: Package,
            href: products(),
        },
        {
            title: 'Add your clients',
            description: 'Then add the products each one uses, at their rate.',
            done: setup.has_clients,
            icon: Users,
            href: clients(),
        },
    ];
    const setupDone = steps.every((step) => step.done);

    const tiles = [
        {
            label: 'Yearly recurring revenue',
            value: formatInr(metrics.annual_recurring),
            note: `${metrics.live_subscriptions} active ${metrics.live_subscriptions === 1 ? 'subscription' : 'subscriptions'}`,
        },
        {
            label: 'Outstanding',
            value: formatInr(metrics.outstanding),
            note:
                metrics.overdue_count > 0
                    ? `${metrics.overdue_count} overdue of ${metrics.outstanding_count} unpaid`
                    : `${metrics.outstanding_count} unpaid ${metrics.outstanding_count === 1 ? 'invoice' : 'invoices'}`,
        },
        {
            label: `Renewals in the next ${lookaheadDays} days`,
            value: formatInr(metrics.renewals_value),
            note: `${metrics.renewals_count} ${metrics.renewals_count === 1 ? 'renewal' : 'renewals'}, before GST`,
        },
    ];

    return (
        <>
            <Head title="Today" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        {now.toLocaleDateString('en-IN', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {greeting(now.getHours())}, {firstName}
                    </h1>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {tiles.map((tile) => (
                        <div
                            key={tile.label}
                            className="space-y-1.5 rounded-xl border bg-card px-5 py-4 shadow-xs"
                        >
                            <p className="text-sm text-muted-foreground">
                                {tile.label}
                            </p>
                            <p className="text-2xl font-semibold tracking-tight">
                                {tile.value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {tile.note}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Today
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {today.length === 0
                                            ? 'Nothing needs you right now.'
                                            : `${today.length} ${today.length === 1 ? 'thing needs' : 'things need'} you. Tick them off or snooze.`}
                                    </p>
                                </div>
                                <Button asChild size="sm" variant="ghost">
                                    <Link href={reminders()}>
                                        All reminders
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                            {today.length > 0 && (
                                <ul className="divide-y">
                                    {today.map((reminder) => (
                                        <ReminderRow
                                            key={reminder.id}
                                            reminder={reminder}
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            <div className="border-b px-5 py-4">
                                <h2 className="text-base font-semibold">
                                    Coming up
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Overdue invoices, then renewals and promo
                                    end dates in the next {lookaheadDays} days.
                                </p>
                            </div>
                            {overdue.length > 0 && (
                                <ol className="divide-y border-b">
                                    {overdue.map((invoice) => (
                                        <li
                                            key={`overdue-${invoice.id}`}
                                            className="flex flex-wrap items-center gap-4 px-5 py-3.5"
                                        >
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                                <AlertTriangle className="size-4" />
                                            </span>
                                            <div className="min-w-56 flex-1">
                                                <p className="font-medium">
                                                    {invoice.client_name} is{' '}
                                                    {invoice.days_overdue}{' '}
                                                    {invoice.days_overdue === 1
                                                        ? 'day'
                                                        : 'days'}{' '}
                                                    overdue
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    <span className="font-mono">
                                                        {invoice.number}
                                                    </span>{' '}
                                                    ·{' '}
                                                    {formatInr(
                                                        invoice.balance_due,
                                                    )}{' '}
                                                    due since{' '}
                                                    {formatDate(
                                                        invoice.due_date,
                                                    )}
                                                    {invoice.days_overdue >
                                                        45 &&
                                                        ' · past the MSME 45-day limit'}
                                                </p>
                                            </div>
                                            <Button asChild size="sm">
                                                <Link
                                                    href={showInvoice(
                                                        invoice.id,
                                                    )}
                                                    prefetch
                                                >
                                                    Remind or record payment
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ol>
                            )}
                            {upcoming.length === 0 && overdue.length === 0 ? (
                                <p className="px-5 py-8 text-center text-sm text-muted-foreground">
                                    Nothing due in the next {lookaheadDays}{' '}
                                    days.
                                </p>
                            ) : (
                                <ol className="divide-y">
                                    {upcoming.map((item) => {
                                        const days = daysFromToday(item.date);
                                        const isRenewal =
                                            item.kind === 'renewal';
                                        const inGrace =
                                            item.kind === 'in_grace';

                                        return (
                                            <li
                                                key={`${item.kind}-${item.subscription_id}`}
                                                className="flex flex-wrap items-center gap-4 px-5 py-3.5"
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                        inGrace
                                                            ? 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300'
                                                            : isRenewal
                                                              ? days <= 7
                                                                  ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300'
                                                                  : 'bg-accent text-accent-foreground'
                                                              : 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300',
                                                    )}
                                                >
                                                    {inGrace ? (
                                                        <AlertTriangle className="size-4" />
                                                    ) : isRenewal ? (
                                                        <RefreshCw className="size-4" />
                                                    ) : (
                                                        <Sparkles className="size-4" />
                                                    )}
                                                </span>
                                                <div className="min-w-56 flex-1">
                                                    <p className="font-medium">
                                                        {inGrace
                                                            ? `${item.client_name} is in the grace period until ${formatDate(item.date)}`
                                                            : isRenewal
                                                              ? `${item.client_name} renews ${whenLabel(item.date)}`
                                                              : `Promo for ${item.client_name} ends ${whenLabel(item.date)}`}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.product_name} ·{' '}
                                                        {formatInr(item.amount)}{' '}
                                                        {
                                                            item.billing_cycle_short
                                                        }{' '}
                                                        ·{' '}
                                                        {inGrace
                                                            ? 'renewal unpaid, record payment or renew manually'
                                                            : isRenewal
                                                              ? `period ends ${formatDate(item.date)}`
                                                              : 'review the rate before renewal'}
                                                    </p>
                                                </div>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={showClient(
                                                            item.client_id,
                                                        )}
                                                        prefetch
                                                    >
                                                        {isRenewal
                                                            ? 'Open'
                                                            : 'Review rate'}
                                                        <ArrowRight />
                                                    </Link>
                                                </Button>
                                            </li>
                                        );
                                    })}
                                </ol>
                            )}
                        </section>

                        {!setupDone && (
                            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                                <div className="border-b px-5 py-4">
                                    <h2 className="text-base font-semibold">
                                        Get set up
                                    </h2>
                                </div>
                                <ol className="divide-y">
                                    {steps.map((step) => (
                                        <li
                                            key={step.title}
                                            className="flex flex-wrap items-center gap-4 px-5 py-4"
                                        >
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                                                <step.icon className="size-[18px]" />
                                            </div>
                                            <div className="min-w-56 flex-1">
                                                <p className="flex items-center gap-2 font-medium">
                                                    {step.done ? (
                                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                                    ) : (
                                                        <Circle className="size-4 text-muted-foreground" />
                                                    )}
                                                    {step.title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {step.description}
                                                </p>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant={
                                                    step.done
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                            >
                                                <Link href={step.href} prefetch>
                                                    {step.done
                                                        ? 'Open'
                                                        : 'Start'}
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}
                    </div>

                    <section className="rounded-xl border bg-card p-5 shadow-xs">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="text-base font-semibold">
                                Business profile
                            </h2>
                            <Button asChild size="sm" variant="outline">
                                <Link href={businessProfile()} prefetch>
                                    Edit
                                </Link>
                            </Button>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <div className="grid gap-0.5">
                                <dt className="text-xs text-muted-foreground">
                                    Name
                                </dt>
                                <dd className="font-medium">
                                    {profile.name ?? 'Not set'}
                                </dd>
                            </div>
                            <div className="grid gap-0.5">
                                <dt className="text-xs text-muted-foreground">
                                    GSTIN
                                </dt>
                                <dd className="font-mono font-medium">
                                    {profile.gstin ?? 'Not set'}
                                    {profile.state && (
                                        <span className="ml-2 font-sans text-xs font-normal text-muted-foreground">
                                            {profile.state}
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div className="grid gap-0.5">
                                <dt className="text-xs text-muted-foreground">
                                    Udyam
                                </dt>
                                <dd className="font-mono font-medium">
                                    {profile.udyam_number ?? 'Not set'}
                                    {profile.msme_category && (
                                        <span className="ml-2 font-sans text-xs font-normal text-muted-foreground">
                                            {profile.msme_category}
                                        </span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                        {profile.missing.length > 0 && (
                            <p className="mt-4 flex gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                <AlertTriangle className="size-4 shrink-0" />
                                Invoices still need:{' '}
                                {profile.missing.join(', ')}.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Today',
            href: dashboard(),
        },
    ],
};
