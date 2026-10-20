import { useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/SubscriptionRateChangeController';
import { Field } from '@/components/crm/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { rupeesToPaise, todayIso } from '@/lib/billing';
import { formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PricingModelValue, Subscription } from '@/types';

const REASONS = [
    'Renewal increase',
    'Discount',
    'Added module',
    'Promo ended',
    'Moved to per-unit pricing',
    'Correction',
];

type Props = {
    subscription: Subscription;
    onDone: () => void;
};

export function RateChangeForm({ subscription, onDone }: Props) {
    const form = useForm({
        pricing_model: subscription.pricing_model,
        rate: subscription.rate_rupees,
        quantity: String(subscription.quantity),
        effective_from: todayIso(),
        reason: '',
    });
    const { data, setData, errors, processing } = form;

    const unit = (subscription.product_unit_label ?? 'per unit').replace(
        /^per\s+/i,
        '',
    );
    const ratePaise = rupeesToPaise(data.rate);
    const newAmount =
        ratePaise === null
            ? null
            : ratePaise *
              (data.pricing_model === 'per_unit'
                  ? Number(data.quantity) || 0
                  : 1);
    const oldAmount = subscription.amount_per_cycle;
    const change =
        newAmount !== null && oldAmount > 0
            ? ((newAmount - oldAmount) / oldAmount) * 100
            : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(subscription.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <ToggleGroup
                type="single"
                variant="outline"
                value={data.pricing_model}
                onValueChange={(value) =>
                    value &&
                    setData('pricing_model', value as PricingModelValue)
                }
                aria-label="Pricing"
                className="w-full"
            >
                <ToggleGroupItem
                    value="flat"
                    className="flex-1 data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground"
                >
                    Flat rate
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="per_unit"
                    className="flex-1 data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground"
                >
                    Per {unit}
                </ToggleGroupItem>
            </ToggleGroup>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="rate_new"
                    label={
                        data.pricing_model === 'per_unit'
                            ? `New rate per ${unit} (₹)`
                            : 'New rate (₹)'
                    }
                    error={errors.rate}
                >
                    <Input
                        id="rate_new"
                        inputMode="decimal"
                        value={data.rate}
                        onChange={(e) => setData('rate', e.target.value)}
                        autoFocus
                    />
                </Field>
                {data.pricing_model === 'per_unit' && (
                    <Field
                        id="rate_quantity"
                        label={`${unit.replace(/^\w/, (c) => c.toUpperCase())}s charged`}
                        error={errors.quantity}
                    >
                        <Input
                            id="rate_quantity"
                            type="number"
                            min={1}
                            value={data.quantity}
                            onChange={(e) =>
                                setData('quantity', e.target.value)
                            }
                        />
                    </Field>
                )}
                <Field
                    id="rate_effective"
                    label="Effective from"
                    error={errors.effective_from}
                >
                    <Input
                        id="rate_effective"
                        type="date"
                        value={data.effective_from}
                        onChange={(e) =>
                            setData('effective_from', e.target.value)
                        }
                    />
                </Field>
            </div>

            <div className="flex flex-wrap items-center gap-2 rounded-lg bg-muted/60 px-3 py-2.5 text-sm">
                <span className="text-muted-foreground">
                    {formatInr(oldAmount)}
                </span>
                <ArrowRight className="size-4 text-muted-foreground" />
                <span className="font-semibold">
                    {newAmount === null ? '—' : formatInr(newAmount)}
                </span>
                <span className="text-muted-foreground">
                    {subscription.billing_cycle_short}
                </span>
                {change !== null && Math.abs(change) >= 0.05 && (
                    <span
                        className={cn(
                            'ml-auto rounded-full px-2 py-0.5 text-xs font-semibold',
                            change > 0
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
                        )}
                    >
                        {change > 0 ? '+' : ''}
                        {change.toFixed(1)}%
                    </span>
                )}
            </div>

            <Field id="rate_reason" label="Reason" error={errors.reason}>
                <div className="flex flex-wrap gap-1.5">
                    {REASONS.map((reason) => (
                        <button
                            key={reason}
                            type="button"
                            onClick={() => setData('reason', reason)}
                            className={cn(
                                'h-7 rounded-full border px-3 text-xs transition-colors',
                                data.reason === reason
                                    ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {reason}
                        </button>
                    ))}
                </div>
                <Input
                    id="rate_reason"
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                    placeholder="Or type your own"
                />
            </Field>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    Save new rate
                </Button>
            </div>
        </form>
    );
}
