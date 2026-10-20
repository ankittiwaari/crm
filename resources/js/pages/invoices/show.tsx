import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    useHttp,
} from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Download,
    Mail,
    MessageCircle,
    Pencil,
    Printer,
    Send,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/InvoiceController';
import {
    email,
    whatsapp,
} from '@/actions/App/Http/Controllers/InvoiceMessageController';
import { destroy as destroyPayment } from '@/actions/App/Http/Controllers/InvoicePaymentController';
import { show as pdf } from '@/actions/App/Http/Controllers/InvoicePdfController';
import { show as showClient } from '@/actions/App/Http/Controllers/ClientController';
import { edit as businessProfile } from '@/routes/business-profile';
import {
    cancel,
    finalise as finaliseRoute,
    whatsappApi,
} from '@/routes/invoices';
import { ConfirmDialog } from '@/components/crm/confirm-dialog';
import { Field } from '@/components/crm/field';
import { InvoiceStatusBadge } from '@/components/crm/invoice-status-badge';
import { PaymentForm } from '@/components/crm/payment-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDate, formatInr, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Invoice, Option } from '@/types';

type InvoiceContact = {
    id: number;
    name: string;
    designation: string | null;
    email: string | null;
    whatsapp_target: string | null;
    receives_invoices: boolean;
    whatsapp_api_blocker: string | null;
};

type Props = {
    invoice: Invoice;
    client: {
        id: number;
        name: string;
        email: string | null;
        contacts: InvoiceContact[];
    };
    renews: { product_name: string; period_end: string; status: string }[];
    missingProfileDetails: string[];
    paymentMethods: Option[];
    linkDays: number;
    whatsappApiReady: boolean;
};

function WhatsAppSendButton({
    invoiceId,
    contact,
}: {
    invoiceId: number;
    contact: InvoiceContact;
}) {
    const http = useHttp<{ contact: number }, { url: string }>({
        contact: contact.id,
    });

    const send = async () => {
        // Open the tab during the click so pop-up blockers allow it.
        const tab = window.open('', '_blank');
        const response = await http.post(whatsapp.url(invoiceId));

        if (tab) {
            tab.location.href = response.url;
        } else {
            window.location.href = response.url;
        }

        router.reload({ only: ['invoice'] });
    };

    return (
        <Button
            type="button"
            variant="outline"
            disabled={http.processing}
            onClick={send}
            className="w-full justify-start border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 hover:text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
        >
            <MessageCircle />
            WhatsApp {contact.name}
            {contact.designation && (
                <span className="truncate font-normal opacity-75">
                    · {contact.designation}
                </span>
            )}
        </Button>
    );
}

function CancelForm({
    invoiceId,
    onDone,
}: {
    invoiceId: number;
    onDone: () => void;
}) {
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(cancel.url(invoiceId), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <Field id="cancel_reason" label="Reason" error={form.errors.reason}>
                <Input
                    id="cancel_reason"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="e.g. Raised against the wrong client"
                    autoFocus
                />
            </Field>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    Keep invoice
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing}
                >
                    Cancel invoice
                </Button>
            </div>
        </form>
    );
}

export default function InvoiceShow({
    invoice,
    client,
    renews,
    missingProfileDetails,
    paymentMethods,
    linkDays,
    whatsappApiReady,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Invoices', href: index() },
            { title: invoice.display_number, href: show(invoice.id) },
        ],
    });

    const [recording, setRecording] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [finalising, setFinalising] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const isDraft = invoice.status === 'draft';
    const isOpen =
        invoice.status === 'issued' || invoice.status === 'partially_paid';
    const whatsappContacts = client.contacts.filter(
        (contact) => contact.whatsapp_target,
    );
    const emailTargets = [
        ...client.contacts
            .filter((contact) => contact.email)
            .map((contact) => ({
                key: `contact-${contact.id}`,
                label: contact.name,
                contactId: contact.id as number | null,
            })),
        ...(client.email
            ? [{ key: 'client', label: client.email, contactId: null }]
            : []),
    ];

    const finalise = () => {
        setFinalising(true);
        router.post(
            finaliseRoute.url(invoice.id),
            {},
            {
                preserveScroll: true,
                onError: (errors) => setErrors(errors),
                onFinish: () => setFinalising(false),
            },
        );
    };

    const sendEmail = (contactId: number | null) =>
        router.post(
            email.url(invoice.id),
            { contact: contactId },
            { preserveScroll: true, onError: (errors) => setErrors(errors) },
        );

    return (
        <>
            <Head title={invoice.display_number} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1
                                className={cn(
                                    'text-2xl font-semibold tracking-tight',
                                    invoice.number && 'font-mono',
                                )}
                            >
                                {invoice.display_number}
                            </h1>
                            <InvoiceStatusBadge invoice={invoice} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={showClient(client.id)}
                                className="font-medium text-foreground hover:underline"
                            >
                                {client.name}
                            </Link>{' '}
                            · {formatDate(invoice.issue_date)} ·{' '}
                            {invoice.tax_type_label}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={pdf.url(invoice.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer />
                                Print
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={pdf.url(invoice.id, {
                                    query: { download: 1 },
                                })}
                            >
                                <Download />
                                PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="overflow-hidden rounded-xl border bg-white shadow-xs">
                        <iframe
                            key={`${invoice.status}-${invoice.amount_settled}`}
                            src={pdf.url(invoice.id)}
                            title={`Invoice ${invoice.display_number}`}
                            className="aspect-[210/297] w-full"
                        />
                    </div>

                    <aside className="flex flex-col gap-4 lg:sticky lg:top-6">
                        <section className="space-y-3 rounded-xl border bg-card p-5 shadow-xs">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {isOpen ? 'Balance due' : 'Total'}
                                </p>
                                <p className="text-3xl font-semibold tracking-tight">
                                    {formatInr(
                                        isOpen
                                            ? invoice.balance_due
                                            : invoice.total,
                                    )}
                                </p>
                            </div>
                            <dl className="grid grid-cols-[1fr_auto] gap-y-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Invoice total
                                </dt>
                                <dd className="text-right">
                                    {formatInr(invoice.total)}
                                </dd>
                                {invoice.amount_settled > 0 && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Received (incl. TDS)
                                        </dt>
                                        <dd className="text-right">
                                            {formatInr(invoice.amount_settled)}
                                        </dd>
                                    </>
                                )}
                                <dt className="text-muted-foreground">Due</dt>
                                <dd
                                    className={cn(
                                        'text-right',
                                        invoice.is_overdue &&
                                            'font-semibold text-red-700 dark:text-red-400',
                                    )}
                                >
                                    {formatDate(invoice.due_date)}
                                    {invoice.is_overdue &&
                                        ` · ${invoice.days_overdue}d late`}
                                </dd>
                            </dl>
                            {renews.length > 0 &&
                                invoice.status !== 'cancelled' && (
                                    <p className="rounded-lg bg-accent px-3 py-2 text-sm text-accent-foreground">
                                        {invoice.status === 'paid'
                                            ? 'Renewed: '
                                            : 'When paid in full, this renews '}
                                        {renews
                                            .map(
                                                (renewal) =>
                                                    `${renewal.product_name} to ${formatDate(renewal.period_end)}`,
                                            )
                                            .join(', ')}
                                        .
                                    </p>
                                )}
                            {invoice.cancellation_reason && (
                                <p className="rounded-lg bg-muted p-3 text-sm">
                                    Cancelled: {invoice.cancellation_reason}
                                </p>
                            )}
                        </section>

                        {Object.values(errors).length > 0 && (
                            <div className="flex gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                <AlertTriangle className="size-4 shrink-0" />
                                <div>
                                    {Object.values(errors).map((message) => (
                                        <p key={message}>{message}</p>
                                    ))}
                                </div>
                            </div>
                        )}

                        {isDraft && (
                            <section className="space-y-3 rounded-xl border bg-card p-5 shadow-xs">
                                {missingProfileDetails.length > 0 ? (
                                    <p className="flex gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            Before issuing, add to your{' '}
                                            <Link
                                                href={businessProfile()}
                                                className="font-medium underline"
                                            >
                                                Business Profile
                                            </Link>
                                            : {missingProfileDetails.join(', ')}
                                            .
                                        </span>
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Issuing gives this invoice the next
                                        number and freezes it. Check the preview
                                        first.
                                    </p>
                                )}
                                <Button
                                    className="w-full"
                                    onClick={finalise}
                                    disabled={
                                        finalising ||
                                        missingProfileDetails.length > 0
                                    }
                                >
                                    <Send />
                                    {finalising ? 'Issuing…' : 'Issue invoice'}
                                </Button>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        asChild
                                    >
                                        <Link href={edit(invoice.id)}>
                                            <Pencil />
                                            Edit
                                        </Link>
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="outline"
                                                className="flex-1"
                                            >
                                                <Trash2 />
                                                Delete
                                            </Button>
                                        }
                                        title="Delete this draft?"
                                        description="Drafts have no number, so deleting one leaves no gap."
                                        confirmLabel="Delete draft"
                                        onConfirm={() =>
                                            router.delete(
                                                destroy.url(invoice.id),
                                            )
                                        }
                                    />
                                </div>
                            </section>
                        )}

                        {isOpen && (
                            <section className="space-y-3 rounded-xl border bg-card p-5 shadow-xs">
                                <Button
                                    className="w-full"
                                    onClick={() => setRecording(true)}
                                >
                                    Record payment
                                </Button>
                                <div className="space-y-2 border-t pt-3">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Send to client
                                    </p>
                                    {whatsappApiReady &&
                                        whatsappContacts.map((contact) => (
                                            <Button
                                                key={`api-${contact.id}`}
                                                className="w-full justify-start"
                                                disabled={
                                                    contact.whatsapp_api_blocker !==
                                                    null
                                                }
                                                title={
                                                    contact.whatsapp_api_blocker ??
                                                    undefined
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        whatsappApi.url(
                                                            invoice.id,
                                                        ),
                                                        { contact: contact.id },
                                                        {
                                                            preserveScroll: true,
                                                            onError: (errors) =>
                                                                setErrors(
                                                                    errors,
                                                                ),
                                                        },
                                                    )
                                                }
                                            >
                                                <Send />
                                                {invoice.is_overdue
                                                    ? 'Send reminder'
                                                    : 'Send invoice'}{' '}
                                                to {contact.name} automatically
                                            </Button>
                                        ))}
                                    {whatsappApiReady &&
                                        whatsappContacts.some(
                                            (contact) =>
                                                contact.whatsapp_api_blocker,
                                        ) && (
                                            <p className="text-xs text-muted-foreground">
                                                {
                                                    whatsappContacts.find(
                                                        (contact) =>
                                                            contact.whatsapp_api_blocker,
                                                    )?.whatsapp_api_blocker
                                                }
                                            </p>
                                        )}
                                    {whatsappContacts.map((contact) => (
                                        <WhatsAppSendButton
                                            key={contact.id}
                                            invoiceId={invoice.id}
                                            contact={contact}
                                        />
                                    ))}
                                    {emailTargets.map((target) => (
                                        <Button
                                            key={target.key}
                                            variant="outline"
                                            className="w-full justify-start"
                                            onClick={() =>
                                                sendEmail(target.contactId)
                                            }
                                        >
                                            <Mail />
                                            Email {target.label}
                                        </Button>
                                    ))}
                                    {whatsappContacts.length === 0 &&
                                        emailTargets.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                Add a contact with a mobile
                                                number or email on the{' '}
                                                <Link
                                                    href={showClient(client.id)}
                                                    className="underline"
                                                >
                                                    client page
                                                </Link>
                                                .
                                            </p>
                                        )}
                                    <p className="text-xs text-muted-foreground">
                                        The message includes a link to the PDF
                                        that works for {linkDays} days.
                                    </p>
                                </div>
                                {invoice.status === 'issued' && (
                                    <Button
                                        variant="ghost"
                                        className="w-full text-red-600 hover:text-red-700"
                                        onClick={() => setCancelling(true)}
                                    >
                                        <Ban />
                                        Cancel invoice
                                    </Button>
                                )}
                            </section>
                        )}

                        {(invoice.payments?.length ?? 0) > 0 && (
                            <section className="rounded-xl border bg-card p-5 shadow-xs">
                                <h2 className="mb-3 text-sm font-semibold">
                                    Payments
                                </h2>
                                <ul className="space-y-3">
                                    {invoice.payments?.map((payment) => (
                                        <li
                                            key={payment.id}
                                            className="group flex items-start gap-3 text-sm"
                                        >
                                            <div className="flex-1">
                                                <p className="font-medium text-emerald-700 dark:text-emerald-400">
                                                    +{formatInr(payment.amount)}
                                                    {payment.tds_amount > 0 && (
                                                        <span className="font-normal text-muted-foreground">
                                                            {' '}
                                                            + TDS{' '}
                                                            {formatInr(
                                                                payment.tds_amount,
                                                            )}
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        payment.method_label,
                                                        formatDate(
                                                            payment.paid_on,
                                                        ),
                                                        payment.reference,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </div>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Remove payment"
                                                        className="size-7 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                }
                                                title="Remove this payment?"
                                                description="Only for payments recorded by mistake. The invoice balance goes back up."
                                                confirmLabel="Remove"
                                                onConfirm={() =>
                                                    router.delete(
                                                        destroyPayment.url(
                                                            payment.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {(invoice.messages?.length ?? 0) > 0 && (
                            <section className="rounded-xl border bg-card p-5 shadow-xs">
                                <h2 className="mb-3 text-sm font-semibold">
                                    Sent
                                </h2>
                                <ul className="space-y-2 text-sm">
                                    {invoice.messages?.map((message) => (
                                        <li
                                            key={message.id}
                                            className="flex items-center gap-2"
                                        >
                                            {message.channel !== 'email' ? (
                                                <MessageCircle className="size-4 text-emerald-600" />
                                            ) : (
                                                <Mail className="size-4 text-muted-foreground" />
                                            )}
                                            <span className="flex-1 truncate">
                                                {message.direction === 'inbound'
                                                    ? `Reply from ${message.recipient}`
                                                    : `${message.channel_label} to ${message.recipient}`}
                                                {message.status &&
                                                    message.status !==
                                                        'received' && (
                                                        <span
                                                            title={
                                                                message.error ??
                                                                undefined
                                                            }
                                                            className={cn(
                                                                'ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                                                message.status ===
                                                                    'failed'
                                                                    ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300'
                                                                    : message.status ===
                                                                        'read'
                                                                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                                                      : 'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {message.status}
                                                        </span>
                                                    )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {timeAgo(message.sent_at)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </aside>
                </div>
            </div>

            <Dialog open={recording} onOpenChange={setRecording}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Record payment</DialogTitle>
                        <DialogDescription>
                            {client.name} · {invoice.display_number} ·{' '}
                            {formatInr(invoice.balance_due)} due
                        </DialogDescription>
                    </DialogHeader>
                    {recording && (
                        <PaymentForm
                            invoice={invoice}
                            methods={paymentMethods}
                            onDone={() => setRecording(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={cancelling} onOpenChange={setCancelling}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Cancel {invoice.display_number}?
                        </DialogTitle>
                        <DialogDescription>
                            The number stays used so your numbering has no gaps.
                            Raise a new invoice if needed.
                        </DialogDescription>
                    </DialogHeader>
                    {cancelling && (
                        <CancelForm
                            invoiceId={invoice.id}
                            onDone={() => setCancelling(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
