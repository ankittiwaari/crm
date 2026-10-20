import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Field } from '@/components/crm/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { store } from '@/routes/subscriptions/renewals';
import type { Subscription } from '@/types';

const REASONS = [
    'Cheque received, not yet cleared',
    'Trusted client, paying next week',
    'Payment promised',
];

export function RenewalOverrideForm({
    subscription,
    onDone,
}: {
    subscription: Subscription;
    onDone: () => void;
}) {
    const form = useForm({ reason: '', waive: false });
    const next = subscription.next_period;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(subscription.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            {next && (
                <p className="rounded-lg bg-accent px-3 py-2.5 text-sm text-accent-foreground">
                    {subscription.product_name} will run{' '}
                    <span className="font-semibold">
                        {formatDate(next.start)} – {formatDate(next.end)}
                    </span>
                    . If an invoice for this period is paid later, it is linked
                    automatically.
                </p>
            )}
            <Field
                id="override_reason"
                label="Reason"
                error={form.errors.reason}
            >
                <div className="flex flex-wrap gap-1.5">
                    {REASONS.map((reason) => (
                        <button
                            key={reason}
                            type="button"
                            onClick={() => form.setData('reason', reason)}
                            className={cn(
                                'h-7 rounded-full border px-3 text-xs transition-colors',
                                form.data.reason === reason
                                    ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {reason}
                        </button>
                    ))}
                </div>
                <Input
                    id="override_reason"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="Or type your own"
                />
            </Field>
            <div className="flex items-start gap-3">
                <Checkbox
                    id="override_waive"
                    checked={form.data.waive}
                    onCheckedChange={(checked) =>
                        form.setData('waive', checked === true)
                    }
                    className="mt-0.5"
                />
                <div className="grid gap-0.5">
                    <Label htmlFor="override_waive">
                        Free extension (won't be invoiced)
                    </Label>
                    <p className="text-xs text-muted-foreground">
                        For goodwill or compensation periods.
                    </p>
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Mark renewed
                </Button>
            </div>
        </form>
    );
}
