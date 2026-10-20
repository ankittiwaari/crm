import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/InvoicePaymentController';
import { Field } from '@/components/crm/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupeesToPaise, todayIso } from '@/lib/billing';
import { formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Invoice, Option } from '@/types';

function toRupees(paise: number): string {
    return (paise / 100).toFixed(2).replace(/\.00$/, '');
}

export function PaymentForm({
    invoice,
    methods,
    onDone,
}: {
    invoice: Invoice;
    methods: Option[];
    onDone: () => void;
}) {
    const form = useForm({
        amount: toRupees(invoice.balance_due),
        tds_amount: '',
        paid_on: todayIso(),
        method: 'upi',
        reference: '',
        notes: '',
    });
    const { data, setData, errors, processing } = form;

    const settles =
        (rupeesToPaise(data.amount) ?? 0) +
        (rupeesToPaise(data.tds_amount) ?? 0);
    const remaining = invoice.balance_due - settles;
    const canDeductTds = invoice.amount_settled === 0;

    const withTds = (percent: number) => {
        const tds = Math.round((invoice.taxable_total * percent) / 100);

        form.setData((values) => ({
            ...values,
            tds_amount: toRupees(tds),
            amount: toRupees(invoice.balance_due - tds),
        }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(invoice.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <Field
                id="pay_amount"
                label="Amount received (₹)"
                error={errors.amount}
            >
                <Input
                    id="pay_amount"
                    inputMode="decimal"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    className="h-12 text-lg font-semibold"
                    autoFocus
                />
                <div className="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        onClick={() =>
                            form.setData((values) => ({
                                ...values,
                                amount: toRupees(invoice.balance_due),
                                tds_amount: '',
                            }))
                        }
                        className="h-7 rounded-full border px-3 text-xs text-muted-foreground hover:text-foreground"
                    >
                        Full {formatInr(invoice.balance_due)}
                    </button>
                    {canDeductTds &&
                        [10, 2].map((percent) => (
                            <button
                                key={percent}
                                type="button"
                                onClick={() => withTds(percent)}
                                className="h-7 rounded-full border px-3 text-xs text-muted-foreground hover:text-foreground"
                            >
                                Full minus {percent}% TDS
                            </button>
                        ))}
                </div>
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="pay_tds"
                    label="TDS deducted (₹)"
                    error={errors.tds_amount}
                    hint="Counts towards settling the invoice"
                >
                    <Input
                        id="pay_tds"
                        inputMode="decimal"
                        value={data.tds_amount}
                        onChange={(e) => setData('tds_amount', e.target.value)}
                        placeholder="0"
                    />
                </Field>
                <Field id="pay_date" label="Received on" error={errors.paid_on}>
                    <Input
                        id="pay_date"
                        type="date"
                        value={data.paid_on}
                        max={todayIso()}
                        onChange={(e) => setData('paid_on', e.target.value)}
                    />
                </Field>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">Method</legend>
                <div
                    role="radiogroup"
                    className="grid grid-cols-3 gap-2 sm:grid-cols-6"
                >
                    {methods.map((method) => (
                        <button
                            key={method.value}
                            type="button"
                            role="radio"
                            aria-checked={data.method === method.value}
                            onClick={() => setData('method', method.value)}
                            className={cn(
                                'h-9 rounded-md border text-xs transition-colors',
                                data.method === method.value
                                    ? 'border-primary bg-accent font-semibold text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {method.label}
                        </button>
                    ))}
                </div>
            </fieldset>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="pay_reference"
                    label="Reference (UTR, cheque no.)"
                    error={errors.reference}
                >
                    <Input
                        id="pay_reference"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                    />
                </Field>
                <Field id="pay_notes" label="Note" error={errors.notes}>
                    <Input
                        id="pay_notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </Field>
            </div>

            <div
                className={cn(
                    'rounded-lg p-3 text-sm',
                    remaining === 0
                        ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                        : remaining < 0
                          ? 'bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200'
                          : 'bg-muted text-foreground',
                )}
            >
                <Label className="sr-only">Result</Label>
                {remaining === 0
                    ? `Settles the invoice in full (${formatInr(settles)}).`
                    : remaining < 0
                      ? `That is ${formatInr(-remaining)} more than the balance due.`
                      : `Settles ${formatInr(settles)}. ${formatInr(remaining)} will still be due.`}
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing || remaining < 0}>
                    Record {formatInr(rupeesToPaise(data.amount) ?? 0)}
                </Button>
            </div>
        </form>
    );
}
