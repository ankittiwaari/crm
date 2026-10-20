import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/SubscriptionController';
import { Field } from '@/components/crm/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { periodContaining, rupeesToPaise, todayIso } from '@/lib/billing';
import { formatDate, formatInr } from '@/lib/format';
import type {
    BillingCycleValue,
    PricingModelValue,
    ProductOption,
    Subscription,
    SubscriptionOptions,
    SubscriptionStatusValue,
} from '@/types';

type Props = {
    clientId: number;
    subscription?: Subscription;
    products: ProductOption[];
    options: SubscriptionOptions;
    onDone: () => void;
};

export function SubscriptionForm({
    clientId,
    subscription,
    products,
    options,
    onDone,
}: Props) {
    const isNew = subscription === undefined;
    const form = useForm({
        product_id: subscription ? String(subscription.product_id) : '',
        status: (subscription?.status ?? 'active') as SubscriptionStatusValue,
        billing_cycle: (subscription?.billing_cycle ??
            'yearly') as BillingCycleValue,
        pricing_model: (subscription?.pricing_model ??
            'flat') as PricingModelValue,
        rate: subscription?.rate_rupees ?? '',
        quantity: subscription ? String(subscription.quantity) : '',
        unit_count: subscription?.unit_count?.toString() ?? '',
        setup_fee: subscription?.setup_fee_rupees ?? '',
        is_promotional: subscription?.is_promotional ?? false,
        promo_ends_on: subscription?.promo_ends_on ?? '',
        promo_note: subscription?.promo_note ?? '',
        start_date: subscription?.start_date ?? todayIso(),
        current_period_end: '',
        auto_renew: subscription?.auto_renew ?? false,
        renewal_notice_days: String(subscription?.renewal_notice_days ?? 30),
        grace_days: String(subscription?.grace_days ?? 15),
        notes: subscription?.notes ?? '',
    });
    const { data, setData, errors, processing } = form;

    const product = products.find((p) => String(p.id) === data.product_id);
    const unitLabel =
        product?.unit_label ?? subscription?.product_unit_label ?? 'per unit';
    const isPerUnit = data.pricing_model === 'per_unit';
    const ratePaise = rupeesToPaise(data.rate);
    const amount =
        ratePaise === null
            ? null
            : ratePaise * (isPerUnit ? Number(data.quantity) || 0 : 1);
    const computedPeriod = periodContaining(
        data.billing_cycle,
        data.start_date,
    );
    const cycleLabel = options.billingCycles
        .find((cycle) => cycle.value === data.billing_cycle)
        ?.label.toLowerCase();

    const chooseProduct = (id: string) => {
        const chosen = products.find((p) => String(p.id) === id);

        form.setData((values) => ({
            ...values,
            product_id: id,
            billing_cycle:
                chosen?.default_billing_cycle ?? values.billing_cycle,
            rate: values.rate || (chosen?.list_price_rupees ?? ''),
        }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isNew) {
            form.post(store.url(clientId), {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.patch(update.url(subscription.id), {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                {isNew && (
                    <>
                        <Field
                            id="sub_product"
                            label="Product"
                            error={errors.product_id}
                        >
                            <Select
                                value={data.product_id}
                                onValueChange={chooseProduct}
                            >
                                <SelectTrigger
                                    id="sub_product"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Choose a product" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={String(p.id)}
                                        >
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <fieldset className="space-y-4 rounded-lg border p-4">
                            <legend className="px-1 text-sm font-medium">
                                Agreed price
                            </legend>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={data.pricing_model}
                                onValueChange={(value) =>
                                    value &&
                                    setData(
                                        'pricing_model',
                                        value as PricingModelValue,
                                    )
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
                                    Per {unitLabel.replace(/^per\s+/i, '')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="sub_rate"
                                    label={
                                        isPerUnit
                                            ? `Rate ${unitLabel} (₹)`
                                            : 'Rate (₹)'
                                    }
                                    error={errors.rate}
                                    hint={`Before GST, ${cycleLabel}`}
                                >
                                    <Input
                                        id="sub_rate"
                                        inputMode="decimal"
                                        value={data.rate}
                                        onChange={(e) =>
                                            setData('rate', e.target.value)
                                        }
                                        placeholder="48000"
                                        required
                                    />
                                </Field>
                                {isPerUnit && (
                                    <Field
                                        id="sub_quantity"
                                        label="Units charged"
                                        error={errors.quantity}
                                    >
                                        <Input
                                            id="sub_quantity"
                                            type="number"
                                            min={1}
                                            value={data.quantity}
                                            onChange={(e) =>
                                                setData(
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            </div>
                            {amount !== null && amount > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Client pays{' '}
                                    <span className="font-semibold text-foreground">
                                        {formatInr(amount)}
                                    </span>{' '}
                                    + GST {cycleLabel}.
                                </p>
                            )}
                        </fieldset>
                    </>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="sub_cycle"
                        label="Billing"
                        error={errors.billing_cycle}
                    >
                        <Select
                            value={data.billing_cycle}
                            onValueChange={(value) =>
                                setData(
                                    'billing_cycle',
                                    value as BillingCycleValue,
                                )
                            }
                        >
                            <SelectTrigger id="sub_cycle" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.billingCycles.map((cycle) => (
                                    <SelectItem
                                        key={cycle.value}
                                        value={cycle.value}
                                    >
                                        {cycle.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="sub_status" label="Status" error={errors.status}>
                        <Select
                            value={data.status}
                            onValueChange={(value) =>
                                setData(
                                    'status',
                                    value as SubscriptionStatusValue,
                                )
                            }
                        >
                            <SelectTrigger id="sub_status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.subscriptionStatuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="sub_start"
                        label="Started on"
                        error={errors.start_date}
                        hint="For existing clients, the date they first started"
                    >
                        <Input
                            id="sub_start"
                            type="date"
                            value={data.start_date}
                            onChange={(e) =>
                                setData('start_date', e.target.value)
                            }
                            required
                        />
                    </Field>
                    {data.billing_cycle !== 'one_time' && (
                        <Field
                            id="sub_period_end"
                            label="Current period ends (optional)"
                            error={errors.current_period_end}
                            hint={
                                data.current_period_end
                                    ? 'Overrides the worked-out date'
                                    : computedPeriod
                                      ? `Worked out: ${formatDate(computedPeriod.start)} – ${formatDate(computedPeriod.end)}`
                                      : subscription?.current_period_end
                                        ? `Now ${formatDate(subscription.current_period_end)}`
                                        : undefined
                            }
                        >
                            <Input
                                id="sub_period_end"
                                type="date"
                                value={data.current_period_end}
                                onChange={(e) =>
                                    setData(
                                        'current_period_end',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    <Field
                        id="sub_unit_count"
                        label={`${(unitLabel.replace(/^per\s+/i, '') || 'unit').replace(/^\w/, (c) => c.toUpperCase())}s on record`}
                        error={errors.unit_count}
                        hint="Track it even on flat deals, for future per-unit pricing"
                    >
                        <Input
                            id="sub_unit_count"
                            type="number"
                            min={0}
                            value={data.unit_count}
                            onChange={(e) =>
                                setData('unit_count', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="sub_setup_fee"
                        label="One-time setup fee (₹)"
                        error={errors.setup_fee}
                    >
                        <Input
                            id="sub_setup_fee"
                            inputMode="decimal"
                            value={data.setup_fee}
                            onChange={(e) =>
                                setData('setup_fee', e.target.value)
                            }
                        />
                    </Field>
                </div>

                <div className="space-y-4 rounded-lg border p-4">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="sub_promo"
                            checked={data.is_promotional}
                            onCheckedChange={(checked) =>
                                setData('is_promotional', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <Label htmlFor="sub_promo">Promotional price</Label>
                            <p className="text-xs text-muted-foreground">
                                You'll get a reminder to review the rate before
                                the promo ends.
                            </p>
                        </div>
                    </div>
                    {data.is_promotional && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="sub_promo_ends"
                                label="Promo ends on"
                                error={errors.promo_ends_on}
                            >
                                <Input
                                    id="sub_promo_ends"
                                    type="date"
                                    value={data.promo_ends_on}
                                    onChange={(e) =>
                                        setData('promo_ends_on', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="sub_promo_note"
                                label="Promo note"
                                error={errors.promo_note}
                            >
                                <Input
                                    id="sub_promo_note"
                                    value={data.promo_note}
                                    onChange={(e) =>
                                        setData('promo_note', e.target.value)
                                    }
                                    placeholder="Launch offer"
                                />
                            </Field>
                        </div>
                    )}
                </div>

                {data.billing_cycle !== 'one_time' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="sub_notice"
                            label="Remind me before renewal (days)"
                            error={errors.renewal_notice_days}
                        >
                            <Input
                                id="sub_notice"
                                type="number"
                                min={0}
                                max={180}
                                value={data.renewal_notice_days}
                                onChange={(e) =>
                                    setData(
                                        'renewal_notice_days',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="sub_grace"
                            label="Grace period after expiry (days)"
                            error={errors.grace_days}
                        >
                            <Input
                                id="sub_grace"
                                type="number"
                                min={0}
                                max={90}
                                value={data.grace_days}
                                onChange={(e) =>
                                    setData('grace_days', e.target.value)
                                }
                            />
                        </Field>
                        <div className="flex items-center gap-3 sm:col-span-2">
                            <Checkbox
                                id="sub_auto_renew"
                                checked={data.auto_renew}
                                onCheckedChange={(checked) =>
                                    setData('auto_renew', checked === true)
                                }
                            />
                            <Label htmlFor="sub_auto_renew">
                                Prepare the renewal invoice automatically
                            </Label>
                        </div>
                    </div>
                )}

                <Field id="sub_notes" label="Deal notes" error={errors.notes}>
                    <Textarea
                        id="sub_notes"
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="e.g. free customisation until March"
                    />
                </Field>
            </div>

            <div className="flex justify-end gap-2 border-t bg-card p-4">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {isNew ? 'Add product' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
