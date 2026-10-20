import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, Sparkles, X } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    create,
    index,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/InvoiceController';
import { Field } from '@/components/crm/field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { formatDate, formatInr } from '@/lib/format';
import { calculateTotals } from '@/lib/invoice';
import { cn } from '@/lib/utils';
import type { InvoiceLine } from '@/types';

type FormData = {
    client_id: number | null;
    issue_date: string;
    due_date: string;
    notes: string | null;
    terms: string | null;
    items: InvoiceLine[];
};

type Props = {
    invoiceId: number | null;
    form: FormData;
    client: {
        id: number;
        name: string;
        gstin: string | null;
        state_name: string | null;
        city: string | null;
    } | null;
    taxType: 'intra_state' | 'inter_state';
    suggestions: InvoiceLine[];
    clients: { id: number; name: string; city: string | null }[];
    products: {
        id: number;
        name: string;
        sac_code: string;
        tax_rate: string;
        list_price_rupees: string;
    }[];
    taxRates: string[];
    defaultSacCode: string;
    paymentTermsDays: number;
    msmeMaxDays: number | null;
};

function addDays(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);
    const result = new Date(Date.UTC(year, month - 1, day + days));

    return result.toISOString().slice(0, 10);
}

function daysBetween(from: string, to: string): number {
    return Math.round((Date.parse(to) - Date.parse(from)) / 86_400_000);
}

export default function InvoiceEdit({
    invoiceId,
    form: initial,
    client,
    taxType,
    suggestions,
    clients,
    products,
    taxRates,
    defaultSacCode,
    msmeMaxDays,
}: Props) {
    const isNew = invoiceId === null;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Invoices', href: index() },
            {
                title: isNew ? 'New invoice' : 'Edit draft',
                href: isNew ? create() : show(invoiceId),
            },
        ],
    });

    const form = useForm<FormData>({
        ...initial,
        notes: initial.notes ?? '',
        terms: initial.terms ?? '',
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;
    const intraState = taxType === 'intra_state';
    const totals = calculateTotals(data.items, intraState);
    const termDays = daysBetween(data.issue_date, data.due_date);

    const usedKeys = new Set(
        data.items.map((item) =>
            item.subscription_id
                ? `${item.subscription_id}-${item.period_start || 'none'}-${item.description}`
                : null,
        ),
    );
    const openSuggestions = suggestions.filter(
        (suggestion) =>
            !usedKeys.has(
                `${suggestion.subscription_id}-${suggestion.period_start || 'none'}-${suggestion.description}`,
            ),
    );

    const setLine = (position: number, changes: Partial<InvoiceLine>) =>
        setData(
            'items',
            data.items.map((item, index) =>
                index === position ? { ...item, ...changes } : item,
            ),
        );

    const addLine = (line?: Partial<InvoiceLine>) =>
        setData('items', [
            ...data.items,
            {
                subscription_id: null,
                product_id: null,
                description: '',
                sac_code: defaultSacCode,
                period_start: '',
                period_end: '',
                quantity: '1',
                unit_price: '',
                discount: '',
                tax_rate: '18',
                ...line,
            },
        ]);

    const chooseClient = (id: string) =>
        router.get(create.url({ query: { client: id } }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isNew) {
            form.post(store.url());
        } else {
            form.put(update.url(invoiceId));
        }
    };

    return (
        <>
            <Head title={isNew ? 'New invoice' : 'Edit draft'} />

            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isNew ? 'New invoice' : 'Edit draft'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Saved as a draft. You'll review it before it gets a
                            number and is issued.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={isNew ? index() : show(invoiceId)}>
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.client_id}
                        >
                            {processing ? 'Saving…' : 'Save draft'}
                        </Button>
                    </div>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <section className="grid gap-4 rounded-xl border bg-card p-5 shadow-xs sm:grid-cols-2">
                            <Field
                                id="client"
                                label="Bill to"
                                error={errors.client_id}
                                className="sm:col-span-2"
                            >
                                {isNew ? (
                                    <Select
                                        value={
                                            data.client_id
                                                ? String(data.client_id)
                                                : ''
                                        }
                                        onValueChange={chooseClient}
                                    >
                                        <SelectTrigger
                                            id="client"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose a client" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {clients.map((option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.name}
                                                    {option.city && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            · {option.city}
                                                        </span>
                                                    )}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <p
                                        id="client"
                                        className="rounded-md border bg-muted/40 px-3 py-2 text-sm font-medium"
                                    >
                                        {client?.name}
                                    </p>
                                )}
                                {client && (
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {[
                                                client.city,
                                                client.state_name,
                                                client.gstin
                                                    ? `GSTIN ${client.gstin}`
                                                    : 'No GSTIN (B2C)',
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                        <span className="rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                            {intraState
                                                ? 'Intra-state · CGST + SGST'
                                                : 'Inter-state · IGST'}
                                        </span>
                                    </div>
                                )}
                            </Field>
                            <Field
                                id="issue_date"
                                label="Invoice date"
                                error={errors.issue_date}
                            >
                                <Input
                                    id="issue_date"
                                    type="date"
                                    value={data.issue_date}
                                    onChange={(e) =>
                                        setData('issue_date', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="due_date"
                                label="Due date"
                                error={errors.due_date}
                                hint={
                                    msmeMaxDays && termDays > msmeMaxDays
                                        ? `More than ${msmeMaxDays} days: beyond the MSME payment limit printed on your invoices.`
                                        : undefined
                                }
                            >
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={data.due_date}
                                    onChange={(e) =>
                                        setData('due_date', e.target.value)
                                    }
                                />
                                <div className="flex flex-wrap gap-1.5">
                                    {[0, 15, 30, 45].map((days) => (
                                        <button
                                            key={days}
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'due_date',
                                                    addDays(
                                                        data.issue_date,
                                                        days,
                                                    ),
                                                )
                                            }
                                            className={cn(
                                                'h-6 rounded-full border px-2.5 text-xs transition-colors',
                                                termDays === days
                                                    ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {days === 0
                                                ? 'On receipt'
                                                : `${days} days`}
                                        </button>
                                    ))}
                                </div>
                            </Field>
                        </section>

                        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <h2 className="text-base font-semibold">
                                    Lines
                                </h2>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Plus />
                                            Add line
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {products.map((product) => (
                                            <DropdownMenuItem
                                                key={product.id}
                                                onSelect={() =>
                                                    addLine({
                                                        product_id: product.id,
                                                        description:
                                                            product.name,
                                                        sac_code:
                                                            product.sac_code,
                                                        tax_rate:
                                                            product.tax_rate,
                                                        unit_price:
                                                            product.list_price_rupees,
                                                    })
                                                }
                                            >
                                                {product.name}
                                            </DropdownMenuItem>
                                        ))}
                                        {products.length > 0 && (
                                            <DropdownMenuSeparator />
                                        )}
                                        <DropdownMenuItem
                                            onSelect={() => addLine()}
                                        >
                                            Custom line (service,
                                            customisation…)
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            {openSuggestions.length > 0 && (
                                <div className="flex flex-wrap items-center gap-2 border-t bg-accent/40 px-5 py-3">
                                    <Sparkles className="size-4 text-accent-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        From {client?.name}'s subscriptions:
                                    </span>
                                    {openSuggestions.map((suggestion) => (
                                        <button
                                            key={suggestion.key}
                                            type="button"
                                            onClick={() => addLine(suggestion)}
                                            className="rounded-full border border-dashed border-primary/50 bg-card px-3 py-1 text-xs font-medium text-accent-foreground transition-colors hover:bg-accent"
                                        >
                                            + {suggestion.description}
                                            {suggestion.period_start &&
                                                ` · ${formatDate(suggestion.period_start)} – ${formatDate(suggestion.period_end)}`}
                                            {suggestion.is_renewal &&
                                                ' (renewal)'}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {data.items.length === 0 ? (
                                <div className="border-t px-5 py-10 text-center text-sm text-muted-foreground">
                                    {client
                                        ? 'Add a line, or pick one suggested from the subscriptions above.'
                                        : 'Choose a client to see lines suggested from their subscriptions.'}
                                    <InputError
                                        className="mt-2"
                                        message={errors.items}
                                    />
                                </div>
                            ) : (
                                <ol className="divide-y border-t">
                                    {data.items.map((item, position) => {
                                        const error = (field: string) =>
                                            fieldErrors[
                                                `items.${position}.${field}`
                                            ];

                                        return (
                                            <li
                                                key={position}
                                                className="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto]"
                                            >
                                                <div className="grid gap-3">
                                                    <div className="grid gap-1.5">
                                                        <Label
                                                            htmlFor={`item_${position}_description`}
                                                            className="sr-only"
                                                        >
                                                            Description
                                                        </Label>
                                                        <Input
                                                            id={`item_${position}_description`}
                                                            value={
                                                                item.description
                                                            }
                                                            onChange={(e) =>
                                                                setLine(
                                                                    position,
                                                                    {
                                                                        description:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            placeholder="Description"
                                                            className="font-medium"
                                                        />
                                                        <InputError
                                                            message={error(
                                                                'description',
                                                            )}
                                                        />
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(5,minmax(0,1fr))]">
                                                        <Field
                                                            id={`item_${position}_quantity`}
                                                            label="Qty"
                                                            error={error(
                                                                'quantity',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_quantity`}
                                                                type="number"
                                                                min={1}
                                                                value={
                                                                    item.quantity
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            quantity:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`item_${position}_price`}
                                                            label="Rate (₹)"
                                                            error={error(
                                                                'unit_price',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_price`}
                                                                inputMode="decimal"
                                                                value={
                                                                    item.unit_price
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            unit_price:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`item_${position}_discount`}
                                                            label="Discount (₹)"
                                                            error={error(
                                                                'discount',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_discount`}
                                                                inputMode="decimal"
                                                                value={
                                                                    item.discount
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            discount:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="0"
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`item_${position}_tax`}
                                                            label="GST"
                                                            error={error(
                                                                'tax_rate',
                                                            )}
                                                        >
                                                            <Select
                                                                value={
                                                                    item.tax_rate
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            tax_rate:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id={`item_${position}_tax`}
                                                                    className="w-full"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {taxRates.map(
                                                                        (
                                                                            rate,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    rate
                                                                                }
                                                                                value={
                                                                                    rate
                                                                                }
                                                                            >
                                                                                {
                                                                                    rate
                                                                                }
                                                                                %
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </Field>
                                                        <Field
                                                            id={`item_${position}_sac`}
                                                            label="SAC"
                                                            error={error(
                                                                'sac_code',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_sac`}
                                                                inputMode="numeric"
                                                                value={
                                                                    item.sac_code
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            sac_code:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                className="font-mono"
                                                            />
                                                        </Field>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-3 sm:max-w-md">
                                                        <Field
                                                            id={`item_${position}_from`}
                                                            label="Period from (optional)"
                                                            error={error(
                                                                'period_start',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_from`}
                                                                type="date"
                                                                value={
                                                                    item.period_start
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            period_start:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`item_${position}_to`}
                                                            label="Period to"
                                                            error={error(
                                                                'period_end',
                                                            )}
                                                        >
                                                            <Input
                                                                id={`item_${position}_to`}
                                                                type="date"
                                                                value={
                                                                    item.period_end
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(
                                                                        position,
                                                                        {
                                                                            period_end:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                    </div>
                                                </div>
                                                <div className="flex items-start justify-between gap-3 sm:flex-col sm:items-end">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Remove line"
                                                        onClick={() =>
                                                            setData(
                                                                'items',
                                                                data.items.filter(
                                                                    (
                                                                        _item,
                                                                        index,
                                                                    ) =>
                                                                        index !==
                                                                        position,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <X />
                                                    </Button>
                                                    <div className="text-right">
                                                        <p className="text-xs text-muted-foreground">
                                                            Taxable
                                                        </p>
                                                        <p className="font-semibold">
                                                            {formatInr(
                                                                totals.lines[
                                                                    position
                                                                ]?.taxable ?? 0,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            )}
                        </section>

                        <section className="grid gap-4 rounded-xl border bg-card p-5 shadow-xs sm:grid-cols-2">
                            <Field
                                id="notes"
                                label="Note to client"
                                error={errors.notes}
                            >
                                <Textarea
                                    id="notes"
                                    rows={3}
                                    value={data.notes ?? ''}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="terms"
                                label="Extra terms"
                                error={errors.terms}
                                hint="Payment terms and the MSME clause are added automatically"
                            >
                                <Textarea
                                    id="terms"
                                    rows={3}
                                    value={data.terms ?? ''}
                                    onChange={(e) =>
                                        setData('terms', e.target.value)
                                    }
                                />
                            </Field>
                        </section>
                    </div>

                    <aside className="rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6">
                        <h2 className="mb-4 text-base font-semibold">
                            Summary
                        </h2>
                        <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                            <dt className="text-muted-foreground">
                                Taxable value
                            </dt>
                            <dd className="text-right">
                                {formatInr(totals.taxable)}
                            </dd>
                            {intraState ? (
                                <>
                                    <dt className="text-muted-foreground">
                                        CGST
                                    </dt>
                                    <dd className="text-right">
                                        {formatInr(totals.cgst)}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        SGST
                                    </dt>
                                    <dd className="text-right">
                                        {formatInr(totals.sgst)}
                                    </dd>
                                </>
                            ) : (
                                <>
                                    <dt className="text-muted-foreground">
                                        IGST
                                    </dt>
                                    <dd className="text-right">
                                        {formatInr(totals.igst)}
                                    </dd>
                                </>
                            )}
                            {totals.roundOff !== 0 && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Round off
                                    </dt>
                                    <dd className="text-right">
                                        {formatInr(totals.roundOff)}
                                    </dd>
                                </>
                            )}
                            <dt className="border-t pt-3 text-base font-semibold">
                                Total
                            </dt>
                            <dd className="border-t pt-3 text-right text-base font-bold">
                                {formatInr(totals.total)}
                            </dd>
                        </dl>
                        <Button
                            type="submit"
                            className="mt-5 w-full"
                            disabled={processing || !data.client_id}
                        >
                            Save draft
                        </Button>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Next you'll see the invoice exactly as it prints,
                            then issue and send it.
                        </p>
                    </aside>
                </div>
            </form>
        </>
    );
}
