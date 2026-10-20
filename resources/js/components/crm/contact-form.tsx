import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ClientContactController';
import { Field } from '@/components/crm/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Contact } from '@/types';

type Props = {
    clientId: number;
    contact?: Contact;
    onDone: () => void;
};

export function ContactForm({ clientId, contact, onDone }: Props) {
    const form = useForm({
        name: contact?.name ?? '',
        designation: contact?.designation ?? '',
        phone: contact?.phone ?? '',
        whatsapp_number: contact?.whatsapp_number ?? '',
        email: contact?.email ?? '',
        whatsapp_opt_in: contact?.whatsapp_opted_in ?? false,
        is_primary: contact?.is_primary ?? false,
        receives_invoices: contact?.receives_invoices ?? false,
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (contact) {
            form.patch(update.url(contact.id), options);
        } else {
            form.post(store.url(clientId), options);
        }
    };

    const flags = [
        {
            key: 'is_primary' as const,
            label: 'Main contact',
            hint: 'Shown first and used for calls.',
        },
        {
            key: 'receives_invoices' as const,
            label: 'Sends invoices and reminders to this person',
            hint: 'Usually the accountant.',
        },
        {
            key: 'whatsapp_opt_in' as const,
            label: 'Agreed to receive WhatsApp messages',
            hint: 'Needed before automatic WhatsApp reminders can be sent.',
        },
    ];

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="contact_name" label="Name" error={errors.name}>
                    <Input
                        id="contact_name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoFocus
                        required
                    />
                </Field>
                <Field
                    id="contact_designation"
                    label="Role"
                    error={errors.designation}
                >
                    <Input
                        id="contact_designation"
                        value={data.designation}
                        onChange={(e) => setData('designation', e.target.value)}
                        placeholder="Principal, Accountant"
                    />
                </Field>
                <Field id="contact_phone" label="Mobile" error={errors.phone}>
                    <Input
                        id="contact_phone"
                        type="tel"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        placeholder="98450 12345"
                    />
                </Field>
                <Field
                    id="contact_whatsapp"
                    label="WhatsApp (if different)"
                    error={errors.whatsapp_number}
                >
                    <Input
                        id="contact_whatsapp"
                        type="tel"
                        value={data.whatsapp_number}
                        onChange={(e) =>
                            setData('whatsapp_number', e.target.value)
                        }
                    />
                </Field>
                <Field
                    id="contact_email"
                    label="Email"
                    error={errors.email}
                    className="sm:col-span-2"
                >
                    <Input
                        id="contact_email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>
            </div>

            <div className="space-y-3">
                {flags.map((flag) => (
                    <div key={flag.key} className="flex items-start gap-3">
                        <Checkbox
                            id={`contact_${flag.key}`}
                            checked={data[flag.key]}
                            onCheckedChange={(checked) =>
                                setData(flag.key, checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <Label htmlFor={`contact_${flag.key}`}>
                                {flag.label}
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                {flag.hint}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {contact ? 'Save contact' : 'Add contact'}
                </Button>
            </div>
        </form>
    );
}
