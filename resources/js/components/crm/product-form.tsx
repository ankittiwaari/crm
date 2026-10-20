import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ProductController';
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
import type { BillingCycleValue, Option, Product } from '@/types';

type Props = {
    product?: Product;
    billingCycles: Option<BillingCycleValue>[];
    taxRates: string[];
    onDone: () => void;
};

export function ProductForm({
    product,
    billingCycles,
    taxRates,
    onDone,
}: Props) {
    const form = useForm({
        name: product?.name ?? '',
        code: product?.code ?? '',
        description: product?.description ?? '',
        sac_code: product?.sac_code ?? '998314',
        tax_rate: product ? String(Number(product.tax_rate)) : '18',
        list_price: product?.list_price_rupees ?? '',
        default_billing_cycle: (product?.default_billing_cycle ??
            'yearly') as BillingCycleValue,
        unit_label: product?.unit_label ?? '',
        is_active: product?.is_active ?? true,
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (product) {
            form.patch(update.url(product.id), { onSuccess: onDone });
        } else {
            form.post(store.url(), { onSuccess: onDone });
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-6">
                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                    <Field id="product_name" label="Name" error={errors.name}>
                        <Input
                            id="product_name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="School ERP"
                            autoFocus
                            required
                        />
                    </Field>
                    <Field
                        id="product_code"
                        label="Short code"
                        error={errors.code}
                    >
                        <Input
                            id="product_code"
                            value={data.code}
                            onChange={(e) =>
                                setData('code', e.target.value.toUpperCase())
                            }
                            placeholder="SERP"
                            maxLength={20}
                            className="font-mono"
                            required
                        />
                    </Field>
                </div>
                <Field
                    id="product_description"
                    label="Description"
                    error={errors.description}
                >
                    <Textarea
                        id="product_description"
                        rows={2}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="product_list_price"
                        label="Starting price (₹, optional)"
                        hint="Only pre-fills new deals. Each client's rate is negotiated."
                        error={errors.list_price}
                    >
                        <Input
                            id="product_list_price"
                            inputMode="decimal"
                            value={data.list_price}
                            onChange={(e) =>
                                setData('list_price', e.target.value)
                            }
                            placeholder="45000"
                        />
                    </Field>
                    <Field
                        id="product_cycle"
                        label="Usual billing"
                        error={errors.default_billing_cycle}
                    >
                        <Select
                            value={data.default_billing_cycle}
                            onValueChange={(value) =>
                                setData(
                                    'default_billing_cycle',
                                    value as BillingCycleValue,
                                )
                            }
                        >
                            <SelectTrigger
                                id="product_cycle"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {billingCycles.map((cycle) => (
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
                    <Field
                        id="product_unit"
                        label="Priced per (optional)"
                        hint="For per-unit deals later, e.g. per student"
                        error={errors.unit_label}
                    >
                        <Input
                            id="product_unit"
                            value={data.unit_label}
                            onChange={(e) =>
                                setData('unit_label', e.target.value)
                            }
                            placeholder="per student"
                        />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field
                            id="product_sac"
                            label="SAC code"
                            error={errors.sac_code}
                        >
                            <Input
                                id="product_sac"
                                inputMode="numeric"
                                value={data.sac_code}
                                onChange={(e) =>
                                    setData('sac_code', e.target.value)
                                }
                                className="font-mono"
                            />
                        </Field>
                        <Field
                            id="product_tax"
                            label="GST"
                            error={errors.tax_rate}
                        >
                            <Select
                                value={data.tax_rate}
                                onValueChange={(value) =>
                                    setData('tax_rate', value)
                                }
                            >
                                <SelectTrigger
                                    id="product_tax"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {taxRates.map((rate) => (
                                        <SelectItem key={rate} value={rate}>
                                            {rate}%
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                </div>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="product_active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <div className="grid gap-0.5">
                        <Label htmlFor="product_active">
                            Currently selling
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            Inactive products stay on existing clients but are
                            hidden when adding new deals.
                        </p>
                    </div>
                </div>
            </div>
            <div className="flex justify-end gap-2 border-t bg-card p-4">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {product ? 'Save product' : 'Add product'}
                </Button>
            </div>
        </form>
    );
}
