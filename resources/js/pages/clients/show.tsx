import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Archive,
    CalendarClock,
    Mail,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Phone,
    Plus,
    Trash2,
    UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    destroy as destroyActivity,
    store as storeActivity,
} from '@/actions/App/Http/Controllers/ClientActivityController';
import { destroy as destroyContact } from '@/actions/App/Http/Controllers/ClientContactController';
import {
    destroy as destroyClient,
    index,
    show,
} from '@/actions/App/Http/Controllers/ClientController';
import {
    create as createInvoice,
    show as showInvoice,
} from '@/actions/App/Http/Controllers/InvoiceController';
import { ClientForm } from '@/components/crm/client-form';
import { ConfirmDialog } from '@/components/crm/confirm-dialog';
import { InvoiceStatusBadge } from '@/components/crm/invoice-status-badge';
import { ContactForm } from '@/components/crm/contact-form';
import { RateChangeForm } from '@/components/crm/rate-change-form';
import { ClientStatusBadge } from '@/components/crm/status-badge';
import { SubscriptionCard } from '@/components/crm/subscription-card';
import { SubscriptionForm } from '@/components/crm/subscription-form';
import { WhatsAppButton } from '@/components/crm/whatsapp-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import {
    formatDate,
    formatInr,
    formatPhone,
    initials,
    isoDateFromToday,
    timeAgo,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    Activity,
    ActivityTypeValue,
    Client,
    ClientOptions,
    Contact,
    Invoice,
    Option,
    ProductOption,
    Subscription,
    SubscriptionOptions,
} from '@/types';

type Props = {
    client: Client;
    activities: Activity[];
    subscriptions: Subscription[];
    invoices: Invoice[];
    outstanding: number;
    products: ProductOption[];
    options: ClientOptions &
        SubscriptionOptions & { activityTypes: Option<ActivityTypeValue>[] };
};

const ACTIVITY_STYLES: Record<ActivityTypeValue, string> = {
    call: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    meeting: 'bg-accent text-accent-foreground',
    demo: 'bg-accent text-accent-foreground',
    email: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    whatsapp:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    note: 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
};

const FOLLOW_UPS = [
    { label: 'No follow-up', days: null },
    { label: 'Tomorrow', days: 1 },
    { label: 'In 3 days', days: 3 },
    { label: 'In a week', days: 7 },
] as const;

function ActivityComposer({
    clientId,
    types,
}: {
    clientId: number;
    types: Option<ActivityTypeValue>[];
}) {
    const form = useForm({
        type: 'call' as ActivityTypeValue,
        body: '',
        follow_up_on: '',
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeActivity.url(clientId), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-3 rounded-lg border bg-background p-3"
        >
            <div
                role="radiogroup"
                aria-label="Activity type"
                className="flex flex-wrap gap-1.5"
            >
                {types.map((type) => (
                    <button
                        key={type.value}
                        type="button"
                        role="radio"
                        aria-checked={data.type === type.value}
                        onClick={() => setData('type', type.value)}
                        className={cn(
                            'h-7 rounded-full border px-3 text-xs transition-colors',
                            data.type === type.value
                                ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                                : 'bg-card text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {type.label}
                    </button>
                ))}
            </div>
            <label htmlFor="activity_body" className="sr-only">
                What happened
            </label>
            <Textarea
                id="activity_body"
                rows={2}
                value={data.body}
                onChange={(event) => setData('body', event.target.value)}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        submit(event);
                    }
                }}
                placeholder="What happened? (Ctrl+Enter to save)"
                className="resize-none border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
            />
            <InputError message={errors.body ?? errors.follow_up_on} />
            <div className="flex flex-wrap items-center gap-1.5">
                <CalendarClock className="size-4 text-muted-foreground" />
                {FOLLOW_UPS.map((option) => {
                    const value =
                        option.days === null
                            ? ''
                            : isoDateFromToday(option.days);

                    return (
                        <button
                            key={option.label}
                            type="button"
                            onClick={() => setData('follow_up_on', value)}
                            className={cn(
                                'h-7 rounded-full px-2.5 text-xs transition-colors',
                                data.follow_up_on === value
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                        </button>
                    );
                })}
                <Input
                    type="date"
                    aria-label="Follow-up date"
                    value={data.follow_up_on}
                    min={isoDateFromToday(0)}
                    onChange={(event) =>
                        setData('follow_up_on', event.target.value)
                    }
                    className="h-7 w-36 text-xs"
                />
                <Button
                    type="submit"
                    size="sm"
                    disabled={processing || data.body.trim() === ''}
                    className="ml-auto"
                >
                    Log
                </Button>
            </div>
        </form>
    );
}

function ContactRow({
    contact,
    onEdit,
}: {
    contact: Contact;
    onEdit: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 py-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                {initials(contact.name)}
            </span>
            <div className="min-w-40 flex-1">
                <p className="flex flex-wrap items-center gap-1.5 font-medium">
                    {contact.name}
                    {contact.is_primary && (
                        <span className="rounded-full bg-accent px-2 py-0.5 text-[11px] font-semibold text-accent-foreground">
                            Main
                        </span>
                    )}
                    {contact.receives_invoices && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                            Gets invoices
                        </span>
                    )}
                </p>
                <p className="text-xs text-muted-foreground">
                    {[
                        contact.designation,
                        formatPhone(contact.phone),
                        contact.email,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {contact.whatsapp_target && (
                    <p className="text-xs text-muted-foreground">
                        WhatsApp{' '}
                        {contact.whatsapp_opted_in ? (
                            <span className="text-emerald-700 dark:text-emerald-400">
                                opted in
                            </span>
                        ) : (
                            'not opted in'
                        )}
                    </p>
                )}
            </div>
            <div className="flex gap-1.5">
                {contact.whatsapp_target && (
                    <WhatsAppButton
                        number={contact.whatsapp_target}
                        name={contact.name}
                        iconOnly
                    />
                )}
                {contact.phone && (
                    <Button asChild variant="outline" size="icon">
                        <a
                            href={`tel:${contact.phone}`}
                            aria-label={`Call ${contact.name}`}
                        >
                            <Phone />
                        </a>
                    </Button>
                )}
                {contact.email && (
                    <Button asChild variant="outline" size="icon">
                        <a
                            href={`mailto:${contact.email}`}
                            aria-label={`Email ${contact.name}`}
                        >
                            <Mail />
                        </a>
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Edit ${contact.name}`}
                    onClick={onEdit}
                >
                    <Pencil />
                </Button>
            </div>
        </li>
    );
}

export default function ClientShow({
    client,
    activities,
    subscriptions,
    invoices,
    outstanding,
    products,
    options,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clients', href: index() },
            { title: client.name, href: show(client.id) },
        ],
    });

    const [editingClient, setEditingClient] = useState(false);
    const [editingContact, setEditingContact] = useState<
        Contact | 'new' | null
    >(null);
    const [editingSubscription, setEditingSubscription] = useState<
        Subscription | 'new' | null
    >(null);
    const [changingRate, setChangingRate] = useState<Subscription | null>(null);
    const annualValue = subscriptions.reduce(
        (total, subscription) => total + subscription.annual_value,
        0,
    );
    const contacts = client.contacts ?? [];
    const primary = contacts.find((contact) => contact.is_primary);
    const whatsapp = primary?.whatsapp_target ?? client.phone;

    const details = [
        { label: 'GSTIN', value: client.gstin, mono: true },
        {
            label: 'Address',
            value: [
                client.billing_address,
                client.city,
                client.state_name &&
                    `${client.state_name} (${client.state_code})`,
                client.pincode,
            ]
                .filter(Boolean)
                .join(', '),
        },
        { label: 'Office phone', value: formatPhone(client.phone) },
        { label: 'Email', value: client.email },
        { label: 'Website', value: client.website },
        { label: 'Source', value: client.source },
        { label: 'Client since', value: formatDate(client.created_at) },
    ].filter((detail) => detail.value);

    return (
        <>
            <Head title={client.name} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8">
                <section className="flex flex-wrap items-start gap-4 rounded-xl border bg-card p-5 shadow-xs sm:p-6">
                    <div className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-lg font-bold text-accent-foreground">
                        {initials(client.name)}
                    </div>
                    <div className="min-w-60 flex-1 space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {client.name}
                            </h1>
                            <ClientStatusBadge
                                status={client.status}
                                label={client.status_label}
                            />
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                {client.type_label}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {[
                                client.city,
                                client.state_name,
                                annualValue > 0 &&
                                    `${formatInr(annualValue)} a year`,
                                client.gstin
                                    ? 'B2B (GST registered)'
                                    : 'B2C (no GSTIN)',
                                client.last_activity_at &&
                                    `Last contact ${timeAgo(client.last_activity_at)}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {whatsapp && (
                            <WhatsAppButton
                                number={whatsapp}
                                name={primary?.name ?? client.name}
                            />
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditingClient(true)}
                        >
                            <Pencil />
                            Edit
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="More actions"
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <ConfirmDialog
                                    trigger={
                                        <DropdownMenuItem
                                            onSelect={(event) =>
                                                event.preventDefault()
                                            }
                                            variant="destructive"
                                        >
                                            <Archive />
                                            Archive client
                                        </DropdownMenuItem>
                                    }
                                    title={`Archive ${client.name}?`}
                                    description="They will disappear from your client list. Their history is kept."
                                    confirmLabel="Archive"
                                    onConfirm={() =>
                                        router.delete(
                                            destroyClient.url(client.id),
                                        )
                                    }
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </section>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <section className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-base font-semibold">
                                    Products in use
                                </h2>
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        setEditingSubscription('new')
                                    }
                                >
                                    <Plus />
                                    Add product
                                </Button>
                            </div>
                            {subscriptions.length === 0 ? (
                                <div className="rounded-xl border border-dashed bg-card p-6 text-sm text-muted-foreground">
                                    Add the products this client uses, with the
                                    rate you agreed. Renewals and invoices are
                                    built from these.
                                </div>
                            ) : (
                                subscriptions.map((subscription) => (
                                    <SubscriptionCard
                                        key={subscription.id}
                                        subscription={subscription}
                                        onEdit={() =>
                                            setEditingSubscription(subscription)
                                        }
                                        onChangeRate={() =>
                                            setChangingRate(subscription)
                                        }
                                    />
                                ))
                            )}
                        </section>

                        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Invoices
                                    </h2>
                                    {outstanding > 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            {formatInr(outstanding)} outstanding
                                        </p>
                                    )}
                                </div>
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={createInvoice({
                                            query: { client: client.id },
                                        })}
                                    >
                                        <Plus />
                                        New invoice
                                    </Link>
                                </Button>
                            </div>
                            {invoices.length === 0 ? (
                                <p className="border-t px-5 py-4 text-sm text-muted-foreground">
                                    No invoices yet.
                                </p>
                            ) : (
                                <ul className="divide-y border-t">
                                    {invoices.map((invoice) => (
                                        <li key={invoice.id}>
                                            <Link
                                                href={showInvoice(invoice.id)}
                                                prefetch
                                                className="flex flex-wrap items-center gap-3 px-5 py-3 text-sm transition-colors hover:bg-muted/50"
                                            >
                                                <span
                                                    className={cn(
                                                        'min-w-36 font-mono text-[13px]',
                                                        !invoice.number &&
                                                            'text-muted-foreground italic',
                                                    )}
                                                >
                                                    {invoice.display_number}
                                                </span>
                                                <span className="flex-1 text-muted-foreground">
                                                    {formatDate(
                                                        invoice.issue_date,
                                                    )}
                                                </span>
                                                <span className="font-medium">
                                                    {formatInr(invoice.total)}
                                                </span>
                                                <InvoiceStatusBadge
                                                    invoice={invoice}
                                                />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-base font-semibold">
                                    Contacts
                                </h2>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditingContact('new')}
                                >
                                    <Plus />
                                    Add contact
                                </Button>
                            </div>
                            {contacts.length === 0 ? (
                                <div className="mt-4 flex items-center gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    <UserRound className="size-5" />
                                    Add the principal, owner or accountant so
                                    you can call or WhatsApp them in one tap.
                                </div>
                            ) : (
                                <ul className="mt-2 divide-y">
                                    {contacts.map((contact) => (
                                        <ContactRow
                                            key={contact.id}
                                            contact={contact}
                                            onEdit={() =>
                                                setEditingContact(contact)
                                            }
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-4 text-base font-semibold">
                                Details
                            </h2>
                            <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                {details.map((detail) => (
                                    <div
                                        key={detail.label}
                                        className="grid gap-0.5"
                                    >
                                        <dt className="text-xs text-muted-foreground">
                                            {detail.label}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'break-words',
                                                detail.mono && 'font-mono',
                                            )}
                                        >
                                            {detail.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {client.notes && (
                                <div className="mt-4 border-t pt-4">
                                    <p className="text-xs text-muted-foreground">
                                        Notes
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-line">
                                        {client.notes}
                                    </p>
                                </div>
                            )}
                        </section>
                    </div>

                    <aside className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs">
                        <h2 className="text-base font-semibold">Activity</h2>
                        <ActivityComposer
                            clientId={client.id}
                            types={options.activityTypes}
                        />
                        {activities.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Calls, demos and notes you log appear here.
                            </p>
                        ) : (
                            <ol className="space-y-4">
                                {activities.map((activity) => (
                                    <li
                                        key={activity.id}
                                        className="group flex gap-3"
                                    >
                                        <span
                                            className={cn(
                                                'flex size-7 shrink-0 items-center justify-center rounded-full',
                                                ACTIVITY_STYLES[activity.type],
                                            )}
                                        >
                                            {activity.type === 'whatsapp' ? (
                                                <MessageCircle className="size-3.5" />
                                            ) : activity.type === 'call' ? (
                                                <Phone className="size-3.5" />
                                            ) : activity.type === 'email' ? (
                                                <Mail className="size-3.5" />
                                            ) : (
                                                <span className="text-[10px] font-bold">
                                                    {activity.type_label[0]}
                                                </span>
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <p className="text-sm whitespace-pre-line">
                                                <span className="font-medium">
                                                    {activity.type_label}
                                                </span>{' '}
                                                · {activity.body}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                <time
                                                    dateTime={
                                                        activity.happened_at
                                                    }
                                                    title={new Date(
                                                        activity.happened_at,
                                                    ).toLocaleString('en-IN')}
                                                >
                                                    {timeAgo(
                                                        activity.happened_at,
                                                    )}
                                                </time>
                                                {activity.follow_up_on && (
                                                    <span className="text-amber-700 dark:text-amber-400">
                                                        {' '}
                                                        · Follow up{' '}
                                                        {formatDate(
                                                            activity.follow_up_on,
                                                        )}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Delete entry"
                                                    className="size-7 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            }
                                            title="Delete this entry?"
                                            description="It will be removed from the client's timeline."
                                            confirmLabel="Delete"
                                            onConfirm={() =>
                                                router.delete(
                                                    destroyActivity.url(
                                                        activity.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    </li>
                                ))}
                            </ol>
                        )}
                    </aside>
                </div>
            </div>

            <Sheet open={editingClient} onOpenChange={setEditingClient}>
                <SheetContent className="w-full gap-0 sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Edit {client.name}</SheetTitle>
                        <SheetDescription>
                            Changes don't affect invoices already issued.
                        </SheetDescription>
                    </SheetHeader>
                    {editingClient && (
                        <ClientForm
                            client={client}
                            options={options}
                            onDone={() => setEditingClient(false)}
                        />
                    )}
                </SheetContent>
            </Sheet>

            <Sheet
                open={editingSubscription !== null}
                onOpenChange={(open) => !open && setEditingSubscription(null)}
            >
                <SheetContent className="w-full gap-0 sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {editingSubscription === 'new'
                                ? `Add a product for ${client.name}`
                                : `Edit ${editingSubscription?.product_name ?? ''} terms`}
                        </SheetTitle>
                        <SheetDescription>
                            {editingSubscription === 'new'
                                ? 'Enter the rate you agreed. Amounts are before GST.'
                                : 'To change the rate, use "Change rate" so it is kept in the history.'}
                        </SheetDescription>
                    </SheetHeader>
                    {editingSubscription !== null && (
                        <SubscriptionForm
                            key={
                                editingSubscription === 'new'
                                    ? 'new'
                                    : editingSubscription.id
                            }
                            clientId={client.id}
                            subscription={
                                editingSubscription === 'new'
                                    ? undefined
                                    : editingSubscription
                            }
                            products={products}
                            options={options}
                            onDone={() => setEditingSubscription(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={changingRate !== null}
                onOpenChange={(open) => !open && setChangingRate(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Change rate · {changingRate?.product_name}
                        </DialogTitle>
                        <DialogDescription>
                            The current rate is kept in the rate history.
                        </DialogDescription>
                    </DialogHeader>
                    {changingRate !== null && (
                        <RateChangeForm
                            key={changingRate.id}
                            subscription={changingRate}
                            onDone={() => setChangingRate(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingContact !== null}
                onOpenChange={(open) => !open && setEditingContact(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingContact === 'new'
                                ? 'Add contact'
                                : `Edit ${editingContact?.name ?? ''}`}
                        </DialogTitle>
                        <DialogDescription>
                            Indian numbers can be typed without +91.
                        </DialogDescription>
                    </DialogHeader>
                    {editingContact !== null && (
                        <ContactForm
                            key={
                                editingContact === 'new'
                                    ? 'new'
                                    : editingContact.id
                            }
                            clientId={client.id}
                            contact={
                                editingContact === 'new'
                                    ? undefined
                                    : editingContact
                            }
                            onDone={() => setEditingContact(null)}
                        />
                    )}
                    {editingContact !== null && editingContact !== 'new' && (
                        <ConfirmDialog
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="justify-self-start text-red-600 hover:text-red-700"
                                >
                                    <Trash2 />
                                    Remove contact
                                </Button>
                            }
                            title={`Remove ${editingContact.name}?`}
                            description="Activities they were part of stay on the timeline."
                            confirmLabel="Remove"
                            onConfirm={() =>
                                router.delete(
                                    destroyContact.url(editingContact.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setEditingContact(null),
                                    },
                                )
                            }
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
