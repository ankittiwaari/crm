import { Head, router } from '@inertiajs/react';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ProductController';
import { ConfirmDialog } from '@/components/crm/confirm-dialog';
import { ProductForm } from '@/components/crm/product-form';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatInr } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/products';
import type { BillingCycleValue, Option, Product } from '@/types';

type Props = {
    products: Product[];
    billingCycles: Option<BillingCycleValue>[];
    taxRates: string[];
};

export default function ProductsIndex({
    products,
    billingCycles,
    taxRates,
}: Props) {
    const [editing, setEditing] = useState<Product | 'new' | null>(null);

    return (
        <>
            <Head title="Products" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Products
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            What you sell. Each client's price is agreed
                            separately, so these are only starting points.
                        </p>
                    </div>
                    <Button onClick={() => setEditing('new')}>
                        <Plus />
                        Add product
                    </Button>
                </div>

                {products.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card px-6 py-16 text-center">
                        <Package className="size-8 text-muted-foreground" />
                        <p className="font-medium">No products yet</p>
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            Add your first product
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {products.map((product) => (
                            <article
                                key={product.id}
                                className={cn(
                                    'flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs',
                                    !product.is_active && 'opacity-70',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 space-y-1">
                                        <h2 className="truncate font-semibold">
                                            {product.name}
                                        </h2>
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-muted-foreground">
                                                {product.code}
                                            </span>
                                            {!product.is_active && (
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                    Not selling
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${product.name}`}
                                            onClick={() => setEditing(product)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Delete ${product.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            }
                                            title={`Delete ${product.name}?`}
                                            description="To keep it on record but stop offering it, edit it and untick “Currently selling” instead."
                                            confirmLabel="Delete product"
                                            onConfirm={() =>
                                                router.delete(
                                                    destroy.url(product.id),
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                {product.usage && (
                                    <p className="text-sm">
                                        {product.usage.clients === 0 ? (
                                            <span className="text-muted-foreground">
                                                No clients yet
                                            </span>
                                        ) : (
                                            <>
                                                <span className="font-medium">
                                                    {product.usage.clients}{' '}
                                                    {product.usage.clients === 1
                                                        ? 'client'
                                                        : 'clients'}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    ·{' '}
                                                    {formatInr(
                                                        product.usage
                                                            .annual_value,
                                                    )}{' '}
                                                    a year
                                                    {product.usage
                                                        .min_annual !== null &&
                                                        product.usage
                                                            .max_annual !==
                                                            null &&
                                                        product.usage.clients >
                                                            1 &&
                                                        ` · deals ${formatInr(product.usage.min_annual)}–${formatInr(product.usage.max_annual)}/yr`}
                                                </span>
                                            </>
                                        )}
                                    </p>
                                )}
                                {product.description && (
                                    <p className="line-clamp-2 text-sm text-muted-foreground">
                                        {product.description}
                                    </p>
                                )}
                                <dl className="mt-auto grid grid-cols-2 gap-3 border-t pt-4 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Starting price
                                        </dt>
                                        <dd className="font-medium">
                                            {product.list_price === null
                                                ? 'Negotiated'
                                                : formatInr(product.list_price)}
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                ·{' '}
                                                {product.default_billing_cycle_label.toLowerCase()}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Tax
                                        </dt>
                                        <dd className="font-medium">
                                            GST {Number(product.tax_rate)}%
                                            <span className="font-mono text-xs font-normal text-muted-foreground">
                                                {' '}
                                                · SAC {product.sac_code}
                                            </span>
                                        </dd>
                                    </div>
                                    {product.unit_label && (
                                        <div className="col-span-2">
                                            <dt className="text-xs text-muted-foreground">
                                                Per-unit pricing
                                            </dt>
                                            <dd>{product.unit_label}</dd>
                                        </div>
                                    )}
                                </dl>
                            </article>
                        ))}
                    </div>
                )}
            </div>

            <Sheet
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <SheetContent className="w-full gap-0 sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {editing === 'new'
                                ? 'Add product'
                                : `Edit ${editing?.name ?? ''}`}
                        </SheetTitle>
                        <SheetDescription>
                            Prices are in rupees, before GST.
                        </SheetDescription>
                    </SheetHeader>
                    {editing !== null && (
                        <ProductForm
                            key={editing === 'new' ? 'new' : editing.id}
                            product={editing === 'new' ? undefined : editing}
                            billingCycles={billingCycles}
                            taxRates={taxRates}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
