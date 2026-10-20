import { useForm } from '@inertiajs/react';
import { Paperclip, X } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    receipt,
    store,
    update,
} from '@/actions/App/Http/Controllers/ExpenseController';
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
import { rupeesToPaise, todayIso } from '@/lib/billing';
import { formatInr } from '@/lib/format';
import type { Expense, Option } from '@/types';

type Props = {
    expense?: Expense;
    copyFrom?: Expense;
    categories: Option[];
    paymentMethods: Option[];
    onDone: () => void;
};

export function ExpenseForm({
    expense,
    copyFrom,
    categories,
    paymentMethods,
    onDone,
}: Props) {
    const source = expense ?? copyFrom;
    const form = useForm({
        spent_on: expense?.spent_on ?? todayIso(),
        category: source?.category ?? 'hosting',
        vendor: source?.vendor ?? '',
        description: source?.description ?? '',
        amount: source?.amount_rupees ?? '',
        gst_amount: source?.gst_amount_rupees ?? '',
        payment_method: source?.payment_method ?? '',
        reference: expense?.reference ?? '',
        is_recurring: source?.is_recurring ?? false,
        notes: expense?.notes ?? '',
        receipt: null as File | null,
        remove_receipt: false,
    });
    const { data, setData, errors, processing } = form;
    const amount = rupeesToPaise(data.amount) ?? 0;
    const gst = rupeesToPaise(data.gst_amount) ?? 0;

    const fillGst = (rate: number) =>
        setData(
            'gst_amount',
            ((amount - amount / (1 + rate / 100)) / 100).toFixed(2),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (expense) {
            form.transform((values) => ({ ...values, _method: 'patch' }));
            form.post(update.url(expense.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(store.url(), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-6">
                <Field
                    id="exp_description"
                    label="What for"
                    error={errors.description}
                >
                    <Input
                        id="exp_description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        placeholder="Server for School ERP"
                        autoFocus
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="exp_date" label="Date" error={errors.spent_on}>
                        <Input
                            id="exp_date"
                            type="date"
                            max={todayIso()}
                            value={data.spent_on}
                            onChange={(e) =>
                                setData('spent_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="exp_category"
                        label="Category"
                        error={errors.category}
                    >
                        <Select
                            value={data.category}
                            onValueChange={(value) =>
                                setData('category', value)
                            }
                        >
                            <SelectTrigger id="exp_category" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="exp_amount"
                        label="Amount paid (₹)"
                        error={errors.amount}
                        hint="Including any GST"
                    >
                        <Input
                            id="exp_amount"
                            inputMode="decimal"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="exp_gst"
                        label="GST you can claim (₹)"
                        error={errors.gst_amount}
                        hint={
                            amount > 0
                                ? `Net cost ${formatInr(Math.max(0, amount - gst))}`
                                : 'Leave empty if there was no GST invoice'
                        }
                    >
                        <Input
                            id="exp_gst"
                            inputMode="decimal"
                            value={data.gst_amount}
                            onChange={(e) =>
                                setData('gst_amount', e.target.value)
                            }
                            placeholder="0"
                        />
                        {amount > 0 && (
                            <div className="flex gap-1.5">
                                {[18, 12, 5].map((rate) => (
                                    <button
                                        key={rate}
                                        type="button"
                                        onClick={() => fillGst(rate)}
                                        className="h-6 rounded-full border px-2.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        {rate}% included
                                    </button>
                                ))}
                            </div>
                        )}
                    </Field>
                    <Field
                        id="exp_vendor"
                        label="Paid to"
                        error={errors.vendor}
                    >
                        <Input
                            id="exp_vendor"
                            value={data.vendor}
                            onChange={(e) => setData('vendor', e.target.value)}
                            placeholder="DigitalOcean"
                        />
                    </Field>
                    <Field
                        id="exp_method"
                        label="Paid by"
                        error={errors.payment_method}
                    >
                        <Select
                            value={data.payment_method || 'none'}
                            onValueChange={(value) =>
                                setData(
                                    'payment_method',
                                    value === 'none' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger id="exp_method" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Not recorded
                                </SelectItem>
                                {paymentMethods.map((method) => (
                                    <SelectItem
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="exp_reference"
                        label="Reference / bill no."
                        error={errors.reference}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="exp_reference"
                            value={data.reference}
                            onChange={(e) =>
                                setData('reference', e.target.value)
                            }
                        />
                    </Field>
                </div>

                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-dashed p-3">
                    <Paperclip className="size-5 text-muted-foreground" />
                    <span className="min-w-40 flex-1 text-sm">
                        {data.receipt
                            ? data.receipt.name
                            : expense?.has_receipt && !data.remove_receipt
                              ? 'Receipt attached'
                              : 'No receipt (PDF or photo, up to 5 MB)'}
                    </span>
                    {expense?.has_receipt &&
                        !data.remove_receipt &&
                        !data.receipt && (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <a href={receipt.url(expense.id)}>View</a>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setData('remove_receipt', true)
                                    }
                                >
                                    <X />
                                    Remove
                                </Button>
                            </>
                        )}
                    <Button type="button" variant="outline" size="sm" asChild>
                        <label className="cursor-pointer">
                            {expense?.has_receipt ? 'Replace' : 'Attach'}
                            <input
                                type="file"
                                accept="application/pdf,image/png,image/jpeg,image/webp"
                                className="sr-only"
                                onChange={(e) =>
                                    setData(
                                        'receipt',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                    </Button>
                    {errors.receipt && (
                        <p className="basis-full text-sm text-red-600">
                            {errors.receipt}
                        </p>
                    )}
                </div>

                <div className="flex items-start gap-3">
                    <Checkbox
                        id="exp_recurring"
                        checked={data.is_recurring}
                        onCheckedChange={(checked) =>
                            setData('is_recurring', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <div className="grid gap-0.5">
                        <Label htmlFor="exp_recurring">Monthly expense</Label>
                        <p className="text-xs text-muted-foreground">
                            Next month it is suggested so you can add it in one
                            click.
                        </p>
                    </div>
                </div>

                <Field id="exp_notes" label="Notes" error={errors.notes}>
                    <Textarea
                        id="exp_notes"
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </Field>
            </div>
            <div className="flex justify-end gap-2 border-t bg-card p-4">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {expense ? 'Save expense' : 'Add expense'}
                </Button>
            </div>
        </form>
    );
}
