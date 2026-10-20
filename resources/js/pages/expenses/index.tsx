import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Paperclip,
    Pencil,
    Plus,
    Receipt,
    Repeat,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/ExpenseController';
import { ConfirmDialog } from '@/components/crm/confirm-dialog';
import { ExpenseForm } from '@/components/crm/expense-form';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate, formatInr } from '@/lib/format';
import type { Expense, Option } from '@/types';

type Props = {
    expenses: Expense[];
    month: string;
    category: string | null;
    summary: {
        month_total: number;
        month_gst: number;
        year_total: number;
        year_net: number;
        financial_year: string;
        by_category: { category: string; label: string; total: number }[];
    };
    recurringToRepeat: Expense[];
    categories: Option[];
    paymentMethods: Option[];
};

type Editing =
    | { mode: 'new' }
    | { mode: 'edit'; expense: Expense }
    | { mode: 'copy'; expense: Expense };

function shiftMonth(month: string, by: number): string {
    const [year, value] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, value - 1 + by, 1));

    return date.toISOString().slice(0, 7);
}

function monthLabel(month: string): string {
    const [year, value] = month.split('-').map(Number);

    return new Date(year, value - 1, 1).toLocaleDateString('en-IN', {
        month: 'long',
        year: 'numeric',
    });
}

export default function ExpensesIndex({
    expenses,
    month,
    category,
    summary,
    recurringToRepeat,
    categories,
    paymentMethods,
}: Props) {
    const [editing, setEditing] = useState<Editing | null>(null);
    const currentMonth = new Date().toISOString().slice(0, 7);
    const largest = Math.max(1, ...summary.by_category.map((row) => row.total));

    const go = (changes: { month?: string; category?: string | null }) =>
        router.get(
            index.url(),
            {
                month: changes.month ?? month,
                category:
                    (changes.category === undefined
                        ? category
                        : changes.category) ?? undefined,
            },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title="Expenses" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Expenses
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Record what you spend. GST you can claim back is
                            kept separate from the real cost.
                        </p>
                    </div>
                    <Button onClick={() => setEditing({ mode: 'new' })}>
                        <Plus />
                        Add expense
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-1 rounded-lg border bg-card p-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label="Previous month"
                            onClick={() => go({ month: shiftMonth(month, -1) })}
                        >
                            <ChevronLeft />
                        </Button>
                        <span className="min-w-36 text-center text-sm font-medium">
                            {monthLabel(month)}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label="Next month"
                            disabled={month >= currentMonth}
                            onClick={() => go({ month: shiftMonth(month, 1) })}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                    <Select
                        value={category ?? 'all'}
                        onValueChange={(value) =>
                            go({ category: value === 'all' ? null : value })
                        }
                    >
                        <SelectTrigger
                            aria-label="Category"
                            className="w-56 bg-card"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {categories.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {recurringToRepeat.length > 0 && (
                    <section className="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-primary/40 bg-accent/40 px-4 py-3">
                        <Repeat className="size-4 text-accent-foreground" />
                        <span className="text-sm text-muted-foreground">
                            Monthly expenses from last month:
                        </span>
                        {recurringToRepeat.map((expense) => (
                            <button
                                key={expense.id}
                                type="button"
                                onClick={() =>
                                    setEditing({ mode: 'copy', expense })
                                }
                                className="rounded-full border bg-card px-3 py-1 text-xs font-medium transition-colors hover:border-primary/40"
                            >
                                + {expense.description} ·{' '}
                                {formatInr(expense.amount)}
                            </button>
                        ))}
                    </section>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b px-5 py-4">
                            <h2 className="text-base font-semibold">
                                {monthLabel(month)}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {formatInr(summary.month_total)} paid
                                {summary.month_gst > 0 &&
                                    ` · ${formatInr(summary.month_gst)} GST to claim`}
                            </p>
                        </div>
                        {expenses.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-6 py-14 text-center text-sm text-muted-foreground">
                                <Receipt className="size-8" />
                                No expenses this month.
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {expenses.map((expense) => (
                                    <li
                                        key={expense.id}
                                        className="group flex flex-wrap items-center gap-3 px-5 py-3"
                                    >
                                        <div className="min-w-48 flex-1">
                                            <p className="flex items-center gap-1.5 font-medium">
                                                {expense.description}
                                                {expense.is_recurring && (
                                                    <Repeat
                                                        className="size-3.5 text-muted-foreground"
                                                        aria-label="Monthly"
                                                    />
                                                )}
                                                {expense.has_receipt && (
                                                    <Paperclip
                                                        className="size-3.5 text-muted-foreground"
                                                        aria-label="Receipt attached"
                                                    />
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {[
                                                    formatDate(
                                                        expense.spent_on,
                                                    ),
                                                    expense.category_label,
                                                    expense.vendor,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-medium">
                                                {formatInr(expense.amount)}
                                            </p>
                                            {expense.gst_amount > 0 && (
                                                <p className="text-xs text-muted-foreground">
                                                    incl.{' '}
                                                    {formatInr(
                                                        expense.gst_amount,
                                                    )}{' '}
                                                    GST
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${expense.description}`}
                                                onClick={() =>
                                                    setEditing({
                                                        mode: 'edit',
                                                        expense,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${expense.description}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title="Delete this expense?"
                                                description="Its receipt is deleted too."
                                                confirmLabel="Delete"
                                                onConfirm={() =>
                                                    router.delete(
                                                        destroy.url(expense.id),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <aside className="space-y-4 rounded-xl border bg-card p-5 shadow-xs">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                FY {summary.financial_year} so far
                            </p>
                            <p className="text-2xl font-semibold tracking-tight">
                                {formatInr(summary.year_net)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Real cost, after claimable GST ·{' '}
                                {formatInr(summary.year_total)} paid
                            </p>
                        </div>
                        {summary.by_category.length > 0 && (
                            <ul
                                className="space-y-3"
                                aria-label="Cost by category this financial year"
                            >
                                {summary.by_category.map((row) => (
                                    <li
                                        key={row.category}
                                        className="space-y-1"
                                    >
                                        <div className="flex justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">
                                                {row.label}
                                            </span>
                                            <span className="font-medium">
                                                {formatInr(row.total)}
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-muted">
                                            <div
                                                className="h-2 rounded-full bg-primary"
                                                style={{
                                                    width: `${(row.total / largest) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </aside>
                </div>
            </div>

            <Sheet
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <SheetContent className="w-full gap-0 sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {editing?.mode === 'edit'
                                ? 'Edit expense'
                                : 'Add expense'}
                        </SheetTitle>
                        <SheetDescription>
                            Amounts in rupees. Attach the bill for your CA.
                        </SheetDescription>
                    </SheetHeader>
                    {editing !== null && (
                        <ExpenseForm
                            key={
                                editing.mode === 'new'
                                    ? 'new'
                                    : `${editing.mode}-${editing.expense.id}`
                            }
                            expense={
                                editing.mode === 'edit'
                                    ? editing.expense
                                    : undefined
                            }
                            copyFrom={
                                editing.mode === 'copy'
                                    ? editing.expense
                                    : undefined
                            }
                            categories={categories}
                            paymentMethods={paymentMethods}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
};
