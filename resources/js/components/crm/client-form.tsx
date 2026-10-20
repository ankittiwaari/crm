import { useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { store, update } from '@/actions/App/Http/Controllers/ClientController';
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
import { checkGstin, normalizeGstin } from '@/lib/gstin';
import type {
    Client,
    ClientOptions,
    ClientStatusValue,
    ClientTypeValue,
} from '@/types';

type Props = {
    client?: Client;
    options: ClientOptions;
    onDone: () => void;
};

export function ClientForm({ client, options, onDone }: Props) {
    const isNew = client === undefined;
    const form = useForm({
        name: client?.name ?? '',
        type: (client?.type ?? 'school') as ClientTypeValue,
        status: (client?.status ?? 'active') as ClientStatusValue,
        phone: client?.phone ?? '',
        email: client?.email ?? '',
        billing_address: client?.billing_address ?? '',
        city: client?.city ?? '',
        state_code: client?.state_code ?? '',
        pincode: client?.pincode ?? '',
        gstin: client?.gstin ?? '',
        source: client?.source ?? '',
        website: client?.website ?? '',
        notes: client?.notes ?? '',
        whatsapp_automation: client?.whatsapp_automation ?? false,
        contact: {
            name: '',
            designation: '',
            phone: '',
            whatsapp_opt_in: false,
        },
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;

    const gstin = checkGstin(
        data.gstin,
        options.states.map((state) => state.code),
    );
    const stateCode =
        gstin.status === 'valid' ? gstin.stateCode : data.state_code;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isNew) {
            form.post(store.url(), { onSuccess: onDone });
        } else {
            form.transform(({ contact: _contact, ...values }) => values);
            form.patch(update.url(client.id), {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="name"
                        label="Name"
                        error={errors.name}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Sunrise Public School"
                            autoFocus
                            required
                        />
                    </Field>
                    <Field id="type" label="Type" error={errors.type}>
                        <Select
                            value={data.type}
                            onValueChange={(value) =>
                                setData('type', value as ClientTypeValue)
                            }
                        >
                            <SelectTrigger id="type" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.types.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="status" label="Status" error={errors.status}>
                        <Select
                            value={data.status}
                            onValueChange={(value) =>
                                setData('status', value as ClientStatusValue)
                            }
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.statuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="phone" label="Office phone" error={errors.phone}>
                        <Input
                            id="phone"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="98450 12345"
                        />
                    </Field>
                    <Field id="email" label="Email" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </Field>
                </div>

                {isNew && (
                    <fieldset className="space-y-4 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-medium">
                            Main contact{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </legend>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="contact_name"
                                label="Name"
                                error={fieldErrors['contact.name']}
                            >
                                <Input
                                    id="contact_name"
                                    value={data.contact.name}
                                    onChange={(e) =>
                                        setData('contact', {
                                            ...data.contact,
                                            name: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id="contact_designation"
                                label="Role"
                                error={fieldErrors['contact.designation']}
                            >
                                <Input
                                    id="contact_designation"
                                    value={data.contact.designation}
                                    onChange={(e) =>
                                        setData('contact', {
                                            ...data.contact,
                                            designation: e.target.value,
                                        })
                                    }
                                    placeholder="Principal, Owner, Accountant"
                                />
                            </Field>
                            <Field
                                id="contact_phone"
                                label="Mobile / WhatsApp"
                                error={fieldErrors['contact.phone']}
                            >
                                <Input
                                    id="contact_phone"
                                    type="tel"
                                    value={data.contact.phone}
                                    onChange={(e) =>
                                        setData('contact', {
                                            ...data.contact,
                                            phone: e.target.value,
                                        })
                                    }
                                    placeholder="98450 12345"
                                />
                            </Field>
                            <div className="flex items-center gap-2 self-end pb-2">
                                <Checkbox
                                    id="contact_whatsapp_opt_in"
                                    checked={data.contact.whatsapp_opt_in}
                                    onCheckedChange={(checked) =>
                                        setData('contact', {
                                            ...data.contact,
                                            whatsapp_opt_in: checked === true,
                                        })
                                    }
                                />
                                <Label htmlFor="contact_whatsapp_opt_in">
                                    Agreed to WhatsApp messages
                                </Label>
                            </div>
                        </div>
                    </fieldset>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="gstin"
                        label="GSTIN (optional)"
                        error={errors.gstin}
                        className="sm:col-span-2"
                        hint={
                            gstin.status === 'valid' ? (
                                <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                                    <ShieldCheck className="size-3.5" />
                                    Valid · B2B invoice, state set from GSTIN
                                </span>
                            ) : gstin.status === 'checksum' ||
                              gstin.status === 'format' ? (
                                <span className="text-red-600 dark:text-red-400">
                                    This doesn't look like a valid GSTIN
                                </span>
                            ) : (
                                'Leave empty for unregistered clients (B2C)'
                            )
                        }
                    >
                        <Input
                            id="gstin"
                            value={data.gstin}
                            onChange={(e) =>
                                setData('gstin', normalizeGstin(e.target.value))
                            }
                            maxLength={15}
                            spellCheck={false}
                            className="font-mono tracking-wider"
                        />
                    </Field>
                    <Field
                        id="billing_address"
                        label="Billing address"
                        error={errors.billing_address}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="billing_address"
                            rows={2}
                            value={data.billing_address}
                            onChange={(e) =>
                                setData('billing_address', e.target.value)
                            }
                        />
                    </Field>
                    <Field id="city" label="City" error={errors.city}>
                        <Input
                            id="city"
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                        />
                    </Field>
                    <div className="grid grid-cols-[minmax(0,1fr)_6.5rem] gap-3">
                        <Field
                            id="state_code"
                            label="State"
                            error={errors.state_code}
                        >
                            <Select
                                value={stateCode}
                                onValueChange={(value) =>
                                    setData('state_code', value)
                                }
                                disabled={gstin.status === 'valid'}
                            >
                                <SelectTrigger
                                    id="state_code"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.states.map((state) => (
                                        <SelectItem
                                            key={state.code}
                                            value={state.code}
                                        >
                                            {state.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field id="pincode" label="PIN" error={errors.pincode}>
                            <Input
                                id="pincode"
                                inputMode="numeric"
                                maxLength={6}
                                value={data.pincode}
                                onChange={(e) =>
                                    setData('pincode', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        id="source"
                        label="How they found you"
                        error={errors.source}
                    >
                        <Input
                            id="source"
                            value={data.source}
                            onChange={(e) => setData('source', e.target.value)}
                            placeholder="Referral, cold call…"
                        />
                    </Field>
                    <Field id="website" label="Website" error={errors.website}>
                        <Input
                            id="website"
                            type="url"
                            value={data.website}
                            onChange={(e) => setData('website', e.target.value)}
                            placeholder="https://"
                        />
                    </Field>
                    <div className="flex items-start gap-3 sm:col-span-2">
                        <Checkbox
                            id="whatsapp_automation"
                            checked={data.whatsapp_automation}
                            onCheckedChange={(checked) =>
                                setData('whatsapp_automation', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <Label htmlFor="whatsapp_automation">
                                Automatic WhatsApp messages
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                Renewal notices, overdue reminders and payment
                                thank-yous go to the billing contact, if they
                                agreed to WhatsApp messages.
                            </p>
                        </div>
                    </div>
                    <Field
                        id="notes"
                        label="Notes"
                        error={errors.notes}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="notes"
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </Field>
                </div>
            </div>

            <div className="flex justify-end gap-2 border-t bg-card p-4">
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing
                        ? 'Saving…'
                        : isNew
                          ? 'Add client'
                          : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
