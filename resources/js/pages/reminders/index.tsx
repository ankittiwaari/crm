import { Head, router, useForm } from '@inertiajs/react';
import { BellRing, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/ReminderController';
import { Field } from '@/components/crm/field';
import { ReminderRow } from '@/components/crm/reminder-row';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isoDateFromToday } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Reminder } from '@/types';

type View = 'due' | 'upcoming' | 'done';

type Props = {
    reminders: Reminder[];
    view: View;
    counts: { due: number; upcoming: number };
    clients: { id: number; name: string }[];
};

function AddReminder({ clients }: { clients: Props['clients'] }) {
    const form = useForm({
        title: '',
        due_on: isoDateFromToday(1),
        client_id: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('title'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border bg-card p-4 shadow-xs sm:grid-cols-[minmax(0,1fr)_10rem_14rem_auto] sm:items-end"
        >
            <Field
                id="reminder_title"
                label="Remind me to…"
                error={form.errors.title}
            >
                <Input
                    id="reminder_title"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Call Green Valley about the transport module"
                />
            </Field>
            <Field id="reminder_due" label="On" error={form.errors.due_on}>
                <Input
                    id="reminder_due"
                    type="date"
                    min={isoDateFromToday(0)}
                    value={form.data.due_on}
                    onChange={(e) => form.setData('due_on', e.target.value)}
                />
            </Field>
            <Field
                id="reminder_client"
                label="Client (optional)"
                error={form.errors.client_id}
            >
                <Select
                    value={form.data.client_id || 'none'}
                    onValueChange={(value) =>
                        form.setData('client_id', value === 'none' ? '' : value)
                    }
                >
                    <SelectTrigger id="reminder_client" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No client</SelectItem>
                        {clients.map((client) => (
                            <SelectItem
                                key={client.id}
                                value={String(client.id)}
                            >
                                {client.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <Button type="submit" disabled={form.processing}>
                <Plus />
                Add
            </Button>
        </form>
    );
}

export default function RemindersIndex({
    reminders,
    view,
    counts,
    clients,
}: Props) {
    const tabs: { value: View; label: string; count?: number }[] = [
        { value: 'due', label: 'Due', count: counts.due },
        { value: 'upcoming', label: 'Upcoming', count: counts.upcoming },
        { value: 'done', label: 'Done' },
    ];

    return (
        <>
            <Head title="Reminders" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 sm:p-8">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Reminders
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Renewals, ending promos and overdue invoices are added
                        every morning. Follow-ups come from the client timeline.
                    </p>
                </div>

                <AddReminder clients={clients} />

                <div
                    role="tablist"
                    aria-label="Reminders"
                    className="flex w-fit gap-1 rounded-lg bg-muted p-1"
                >
                    {tabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            role="tab"
                            aria-selected={view === tab.value}
                            onClick={() =>
                                router.get(
                                    index.url(),
                                    tab.value === 'due'
                                        ? {}
                                        : { view: tab.value },
                                    { preserveScroll: true },
                                )
                            }
                            className={cn(
                                'h-8 rounded-md px-3 text-sm transition-colors',
                                view === tab.value
                                    ? 'bg-card font-medium text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                            {tab.count !== undefined && (
                                <span className="ml-1 text-xs text-muted-foreground">
                                    {tab.count}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {reminders.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-card px-6 py-14 text-center">
                        <BellRing className="size-8 text-muted-foreground" />
                        <p className="font-medium">
                            {view === 'due'
                                ? 'All clear for today'
                                : view === 'upcoming'
                                  ? 'Nothing scheduled yet'
                                  : 'Nothing completed yet'}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                        {reminders.map((reminder) => (
                            <ReminderRow
                                key={reminder.id}
                                reminder={reminder}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

RemindersIndex.layout = {
    breadcrumbs: [{ title: 'Reminders', href: index() }],
};
