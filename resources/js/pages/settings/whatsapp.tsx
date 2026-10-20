import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Copy,
    ExternalLink,
    PlugZap,
    RefreshCw,
    Send,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Field } from '@/components/crm/field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    edit,
    syncTemplates,
    test,
    testMessage,
    update,
} from '@/routes/whatsapp-settings';

type Definition = {
    label: string;
    header: string | null;
    body: string;
    variables: string[];
};

type Props = {
    settings: {
        enabled: boolean;
        waba_id: string | null;
        phone_number_id: string | null;
        display_number: string | null;
        has_access_token: boolean;
        has_app_secret: boolean;
        verify_token: string;
        templates: Record<string, { name: string; language: string }>;
        template_statuses: Record<string, string>;
        templates_synced_at: string | null;
        ready: boolean;
    };
    webhookUrl: string;
    definitions: Record<string, Definition>;
};

function CopyField({
    id,
    label,
    value,
}: {
    id: string;
    label: string;
    value: string;
}) {
    const [copiedText, copy] = useClipboard();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={value}
                    readOnly
                    className="font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Copy ${label}`}
                    onClick={() => copy(value)}
                >
                    {copiedText === value ? <CheckCircle2 /> : <Copy />}
                </Button>
            </div>
        </div>
    );
}

function StatusChip({ status }: { status: string | undefined }) {
    if (!status) {
        return (
            <span className="text-xs text-muted-foreground">Not synced</span>
        );
    }

    const approved = status === 'APPROVED';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                approved
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                    : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
            )}
        >
            {approved ? (
                <CheckCircle2 className="size-3" />
            ) : (
                <XCircle className="size-3" />
            )}
            {status.toLowerCase().replace(/_/g, ' ')}
        </span>
    );
}

export default function WhatsAppSettings({
    settings,
    webhookUrl,
    definitions,
}: Props) {
    const { errors: pageErrors } = usePage().props as {
        errors: Record<string, string>;
    };
    const [testNumber, setTestNumber] = useState('');
    const types = Object.keys(definitions);

    const form = useForm({
        enabled: settings.enabled,
        waba_id: settings.waba_id ?? '',
        phone_number_id: settings.phone_number_id ?? '',
        display_number: settings.display_number ?? '',
        access_token: '',
        app_secret: '',
        templates: Object.fromEntries(
            types.map((type) => [
                type,
                {
                    name: settings.templates[type]?.name ?? '',
                    language: settings.templates[type]?.language ?? 'en',
                },
            ]),
        ) as Record<string, { name: string; language: string }>,
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(update.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('access_token', 'app_secret'),
        });
    };

    const post = (url: string, payload: Record<string, string> = {}) =>
        router.post(url, payload, { preserveScroll: true });

    return (
        <>
            <Head title="WhatsApp settings" />

            <h1 className="sr-only">WhatsApp settings</h1>

            <div className="space-y-10">
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="WhatsApp Cloud API"
                        description="Send invoices, reminders and thank-yous automatically from your own WhatsApp Business number."
                    />
                    <div
                        className={cn(
                            'flex items-start gap-3 rounded-lg p-4 text-sm',
                            settings.ready
                                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                                : 'bg-muted',
                        )}
                    >
                        <PlugZap className="mt-0.5 size-4 shrink-0" />
                        <div className="space-y-1">
                            <p className="font-medium">
                                {settings.ready
                                    ? `Connected${settings.display_number ? ` · ${settings.display_number}` : ''}`
                                    : 'Not connected yet'}
                            </p>
                            <p>
                                Use a separate Meta app for the CRM (a Business
                                app as a Direct Developer, no app review
                                needed), with its own phone number and a System
                                User token that can only access this WhatsApp
                                Business Account.
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-8">
                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold">1. Connection</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="wa_waba"
                                label="WhatsApp Business Account ID"
                                error={errors.waba_id}
                            >
                                <Input
                                    id="wa_waba"
                                    inputMode="numeric"
                                    value={data.waba_id}
                                    onChange={(e) =>
                                        setData('waba_id', e.target.value)
                                    }
                                    className="font-mono"
                                />
                            </Field>
                            <Field
                                id="wa_phone_id"
                                label="Phone number ID"
                                error={errors.phone_number_id}
                                hint="Under WhatsApp › API Setup in the app dashboard"
                            >
                                <Input
                                    id="wa_phone_id"
                                    inputMode="numeric"
                                    value={data.phone_number_id}
                                    onChange={(e) =>
                                        setData(
                                            'phone_number_id',
                                            e.target.value,
                                        )
                                    }
                                    className="font-mono"
                                />
                            </Field>
                            <Field
                                id="wa_display"
                                label="WhatsApp number"
                                error={errors.display_number}
                            >
                                <Input
                                    id="wa_display"
                                    type="tel"
                                    value={data.display_number}
                                    onChange={(e) =>
                                        setData(
                                            'display_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="98450 12345"
                                />
                            </Field>
                            <Field
                                id="wa_token"
                                label="Permanent access token"
                                error={errors.access_token}
                                hint={
                                    settings.has_access_token
                                        ? 'Saved (hidden). Type a new one only to replace it.'
                                        : 'System User token with whatsapp_business_messaging and whatsapp_business_management'
                                }
                            >
                                <Input
                                    id="wa_token"
                                    type="password"
                                    autoComplete="off"
                                    value={data.access_token}
                                    onChange={(e) =>
                                        setData('access_token', e.target.value)
                                    }
                                    placeholder={
                                        settings.has_access_token
                                            ? '••••••••••••'
                                            : ''
                                    }
                                />
                            </Field>
                        </div>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="wa_enabled"
                                checked={data.enabled}
                                onCheckedChange={(checked) =>
                                    setData('enabled', checked === true)
                                }
                                className="mt-0.5"
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor="wa_enabled">
                                    Send WhatsApp messages through the API
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Turn off to pause all automatic messages.
                                    Click-to-chat keeps working.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold">2. Webhook</h2>
                        <p className="text-sm text-muted-foreground">
                            In the app dashboard under WhatsApp › Configuration,
                            set this callback URL and verify token, then
                            subscribe to the <code>messages</code> and{' '}
                            <code>message_template_status_update</code> fields.
                            Delivery ticks and client replies then show up here.
                        </p>
                        <div className="grid gap-4">
                            <CopyField
                                id="wa_webhook"
                                label="Callback URL"
                                value={webhookUrl}
                            />
                            <CopyField
                                id="wa_verify"
                                label="Verify token"
                                value={settings.verify_token}
                            />
                            <Field
                                id="wa_secret"
                                label="App secret"
                                error={errors.app_secret}
                                hint={
                                    settings.has_app_secret
                                        ? 'Saved (hidden). Used to check that webhooks really come from Meta.'
                                        : 'App settings › Basic › App secret. Webhooks are rejected until it is set.'
                                }
                            >
                                <Input
                                    id="wa_secret"
                                    type="password"
                                    autoComplete="off"
                                    value={data.app_secret}
                                    onChange={(e) =>
                                        setData('app_secret', e.target.value)
                                    }
                                    placeholder={
                                        settings.has_app_secret
                                            ? '••••••••••••'
                                            : ''
                                    }
                                />
                            </Field>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-sm font-semibold">
                                3. Message templates
                            </h2>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => post(syncTemplates.url())}
                            >
                                <RefreshCw />
                                Sync from Meta
                            </Button>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Create each template in WhatsApp Manager (category
                            Utility) with exactly this text, then enter its
                            name.
                            {settings.templates_synced_at &&
                                ` Statuses last synced ${formatDate(settings.templates_synced_at)}.`}
                        </p>
                        <InputError message={pageErrors.templates} />
                        <ul className="space-y-4">
                            {types.map((type) => {
                                const definition = definitions[type];
                                const name = data.templates[type]?.name;

                                return (
                                    <li
                                        key={type}
                                        className="space-y-3 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">
                                                {definition.label}
                                            </p>
                                            {name && (
                                                <StatusChip
                                                    status={
                                                        settings
                                                            .template_statuses[
                                                            name
                                                        ]
                                                    }
                                                />
                                            )}
                                        </div>
                                        <div className="rounded-md bg-muted px-3 py-2 text-xs leading-relaxed">
                                            {definition.header && (
                                                <p className="mb-1 font-medium">
                                                    Header: {definition.header}
                                                </p>
                                            )}
                                            <p>{definition.body}</p>
                                            <p className="mt-1 text-muted-foreground">
                                                {definition.variables
                                                    .map(
                                                        (variable, index) =>
                                                            `{{${index + 1}}} ${variable}`,
                                                    )
                                                    .join(' · ')}
                                            </p>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]">
                                            <Field
                                                id={`tpl_${type}`}
                                                label="Template name"
                                                error={
                                                    fieldErrors[
                                                        `templates.${type}.name`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`tpl_${type}`}
                                                    value={
                                                        data.templates[type]
                                                            ?.name ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        setData('templates', {
                                                            ...data.templates,
                                                            [type]: {
                                                                ...data
                                                                    .templates[
                                                                    type
                                                                ],
                                                                name: e.target
                                                                    .value,
                                                            },
                                                        })
                                                    }
                                                    placeholder={type}
                                                    className="font-mono"
                                                />
                                            </Field>
                                            <Field
                                                id={`tpl_${type}_lang`}
                                                label="Language"
                                                error={
                                                    fieldErrors[
                                                        `templates.${type}.language`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`tpl_${type}_lang`}
                                                    value={
                                                        data.templates[type]
                                                            ?.language ?? 'en'
                                                    }
                                                    onChange={(e) =>
                                                        setData('templates', {
                                                            ...data.templates,
                                                            [type]: {
                                                                ...data
                                                                    .templates[
                                                                    type
                                                                ],
                                                                language:
                                                                    e.target
                                                                        .value,
                                                            },
                                                        })
                                                    }
                                                    className="font-mono"
                                                />
                                            </Field>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <Button type="submit" disabled={processing}>
                        Save WhatsApp settings
                    </Button>
                </form>

                <section className="space-y-4 border-t pt-8">
                    <h2 className="text-sm font-semibold">4. Test</h2>
                    <div className="flex flex-wrap items-start gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => post(test.url())}
                        >
                            <PlugZap />
                            Test connection
                        </Button>
                        <InputError
                            message={pageErrors.connection}
                            className="basis-full"
                        />
                    </div>
                    <div className="grid gap-2 sm:max-w-md">
                        <Label htmlFor="wa_test_to">
                            Send Meta's "hello_world" test message to
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="wa_test_to"
                                type="tel"
                                value={testNumber}
                                onChange={(e) => setTestNumber(e.target.value)}
                                placeholder="98450 12345"
                            />
                            <Button
                                type="button"
                                onClick={() =>
                                    post(testMessage.url(), { to: testNumber })
                                }
                                disabled={testNumber.trim() === ''}
                            >
                                <Send />
                                Send
                            </Button>
                        </div>
                        <InputError message={pageErrors.to} />
                    </div>
                    <a
                        href="https://business.facebook.com/wa/manage/message-templates/"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        Open WhatsApp Manager
                        <ExternalLink className="size-3.5" />
                    </a>
                </section>
            </div>
        </>
    );
}

WhatsAppSettings.layout = {
    breadcrumbs: [{ title: 'WhatsApp settings', href: edit() }],
};
