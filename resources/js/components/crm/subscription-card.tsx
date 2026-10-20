import { Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    MoreHorizontal,
    Pencil,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { create as createInvoice } from '@/actions/App/Http/Controllers/InvoiceController';
import { destroy } from '@/actions/App/Http/Controllers/SubscriptionController';
import { ConfirmDialog } from '@/components/crm/confirm-dialog';
import { RenewalOverrideForm } from '@/components/crm/renewal-override-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { periodProgress } from '@/lib/billing';
import { formatDate, formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import { destroy as undoRenewal } from '@/routes/renewals';
import type { Subscription } from '@/types';

function renewalTone(subscription: Subscription): {
    text: string;
    className: string;
    bar: string;
} {
    const days = subscription.days_until_renewal;

    if (days === null) {
        return { text: '', className: '', bar: 'bg-primary' };
    }

    if (subscription.renewal_state === 'in_grace') {
        return {
            text: `Renewal unpaid · grace until ${formatDate(subscription.grace_ends_on)}`,
            className: 'text-orange-700 dark:text-orange-400',
            bar: 'bg-orange-500',
        };
    }

    if (subscription.status === 'expired') {
        return {
            text: 'Expired · not renewed',
            className: 'text-red-700 dark:text-red-400',
            bar: 'bg-red-600',
        };
    }

    if (days < 0) {
        return {
            text: `Period ended ${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'} ago`,
            className: 'text-red-700 dark:text-red-400',
            bar: 'bg-red-600',
        };
    }

    const text =
        days === 0
            ? 'Renews today'
            : `Renews in ${days} ${days === 1 ? 'day' : 'days'}`;

    if (days <= 7) {
        return {
            text,
            className: 'text-red-700 dark:text-red-400',
            bar: 'bg-red-600',
        };
    }

    if (days <= subscription.renewal_notice_days) {
        return {
            text,
            className: 'text-amber-700 dark:text-amber-400',
            bar: 'bg-amber-500',
        };
    }

    return { text, className: 'text-muted-foreground', bar: 'bg-primary' };
}

export function SubscriptionCard({
    subscription,
    onEdit,
    onChangeRate,
}: {
    subscription: Subscription;
    onEdit: () => void;
    onChangeRate: () => void;
}) {
    const unit = (subscription.product_unit_label ?? 'per unit').replace(
        /^per\s+/i,
        '',
    );
    const isPerUnit = subscription.pricing_model === 'per_unit';
    const tone = renewalTone(subscription);
    const history = [...(subscription.rate_revisions ?? [])].reverse();
    const [renewing, setRenewing] = useState(false);
    const canRenew =
        subscription.next_period !== null &&
        (subscription.is_live || subscription.status === 'expired');
    const periods = subscription.periods ?? [];

    const summary = [
        isPerUnit
            ? `${formatInr(subscription.rate)} × ${subscription.quantity} ${unit}s`
            : 'Flat rate',
        subscription.billing_cycle_label.toLowerCase(),
        !isPerUnit &&
            subscription.unit_count &&
            `${subscription.unit_count} ${unit}s on record`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <article
            className={cn(
                'space-y-4 rounded-xl border bg-card p-5 shadow-xs',
                !subscription.is_live && 'opacity-75',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold">
                            {subscription.product_name}
                        </h3>
                        {subscription.is_promotional && (
                            <span className="rounded-full bg-fuchsia-50 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300">
                                Promo
                                {subscription.promo_ends_on &&
                                    ` · until ${formatDate(subscription.promo_ends_on)}`}
                            </span>
                        )}
                        {subscription.status !== 'active' && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                                {subscription.status_label}
                            </span>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">{summary}</p>
                </div>
                <div className="text-right">
                    <p className="text-xl font-semibold tracking-tight">
                        {formatInr(subscription.amount_per_cycle)}
                        <span className="text-sm font-normal text-muted-foreground">
                            {' '}
                            {subscription.billing_cycle_short} + GST
                        </span>
                    </p>
                    {!isPerUnit && subscription.unit_count ? (
                        <p className="text-xs text-muted-foreground">
                            ≈{' '}
                            {formatInr(
                                Math.round(
                                    subscription.amount_per_cycle /
                                        subscription.unit_count,
                                ),
                            )}{' '}
                            per {unit}
                        </p>
                    ) : null}
                </div>
            </div>

            {subscription.current_period_start &&
                subscription.current_period_end && (
                    <div className="space-y-2">
                        <div className="flex flex-wrap justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Current period{' '}
                                {formatDate(subscription.current_period_start)}{' '}
                                – {formatDate(subscription.current_period_end)}
                            </span>
                            <span
                                className={cn('font-semibold', tone.className)}
                            >
                                {tone.text}
                            </span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label="Current period elapsed"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(
                                periodProgress(
                                    subscription.current_period_start,
                                    subscription.current_period_end,
                                ) * 100,
                            )}
                            className="h-2 rounded-full bg-muted"
                        >
                            <div
                                className={cn('h-2 rounded-full', tone.bar)}
                                style={{
                                    width: `${periodProgress(subscription.current_period_start, subscription.current_period_end) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                )}

            <div className="flex flex-wrap gap-2">
                {subscription.is_live && (
                    <Button size="sm" asChild>
                        <Link
                            href={createInvoice({
                                query: {
                                    client: subscription.client_id,
                                    subscription: subscription.id,
                                },
                            })}
                        >
                            {subscription.days_until_renewal !== null &&
                            subscription.days_until_renewal <=
                                subscription.renewal_notice_days
                                ? 'Create renewal invoice'
                                : 'Create invoice'}
                        </Link>
                    </Button>
                )}
                {canRenew && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setRenewing(true)}
                    >
                        <CheckCircle2 />
                        Mark renewed…
                    </Button>
                )}
                <Button size="sm" variant="outline" onClick={onChangeRate}>
                    Change rate…
                </Button>
                <Button size="sm" variant="outline" onClick={onEdit}>
                    <Pencil />
                    Edit terms
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label="More subscription actions"
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <ConfirmDialog
                            trigger={
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={(event) => event.preventDefault()}
                                >
                                    <Trash2 />
                                    Remove (added by mistake)
                                </DropdownMenuItem>
                            }
                            title={`Remove ${subscription.product_name}?`}
                            description="Only for subscriptions added by mistake. If the client stopped using it, edit the terms and set the status to Cancelled instead."
                            confirmLabel="Remove"
                            onConfirm={() =>
                                router.delete(destroy.url(subscription.id), {
                                    preserveScroll: true,
                                })
                            }
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {periods.length > 0 && (
                <div className="space-y-2 border-t pt-4">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Renewals
                    </p>
                    <ul className="space-y-1.5 text-sm">
                        {periods.map((period) => (
                            <li
                                key={period.id}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <span className="text-muted-foreground">
                                    {formatDate(period.period_start)} –{' '}
                                    {formatDate(period.period_end)}
                                </span>
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        period.status === 'paid'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : period.status ===
                                                'pending_payment'
                                              ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                              : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
                                    )}
                                >
                                    {period.status_label}
                                </span>
                                {period.override_reason && (
                                    <span className="text-xs text-muted-foreground">
                                        {period.override_reason}
                                    </span>
                                )}
                                {period.can_undo && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                            >
                                                <Undo2 />
                                                Undo
                                            </Button>
                                        }
                                        title="Undo this manual renewal?"
                                        description="The subscription goes back to its previous period."
                                        confirmLabel="Undo renewal"
                                        onConfirm={() =>
                                            router.delete(
                                                undoRenewal.url(period.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {history.length > 0 && (
                <div className="space-y-2 border-t pt-4">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Rate history
                    </p>
                    <ol className="flex flex-wrap items-center gap-2 text-xs">
                        {history.map((revision, position) => (
                            <li
                                key={revision.id}
                                className="flex items-center gap-2"
                            >
                                {position > 0 && (
                                    <ArrowRight
                                        className="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                                <span
                                    className={cn(
                                        'rounded-md px-2 py-1',
                                        position === history.length - 1
                                            ? 'bg-accent text-accent-foreground'
                                            : 'bg-muted',
                                    )}
                                >
                                    <span className="font-semibold">
                                        {formatInr(revision.new_amount)}
                                    </span>{' '}
                                    ·{' '}
                                    {new Date(
                                        revision.effective_from,
                                    ).toLocaleDateString('en-IN', {
                                        month: 'short',
                                        year: 'numeric',
                                    })}{' '}
                                    · {revision.reason}
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            <Dialog open={renewing} onOpenChange={setRenewing}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Mark {subscription.product_name} renewed
                        </DialogTitle>
                        <DialogDescription>
                            Renews without waiting for full payment. You can
                            undo this while the period is unpaid.
                        </DialogDescription>
                    </DialogHeader>
                    {renewing && (
                        <RenewalOverrideForm
                            subscription={subscription}
                            onDone={() => setRenewing(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </article>
    );
}
