import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    FileText,
    History,
    ShieldCheck,
    Upload,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import BusinessProfileController from '@/actions/App/Http/Controllers/BusinessProfileController';
import { Field } from '@/components/crm/field';
import InputError from '@/components/input-error';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { checkGstin, normalizeGstin } from '@/lib/gstin';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/business-profile';
import type {
    BusinessProfile,
    BusinessProfileChange,
    IndianStateOption,
    MsmeCategoryValue,
} from '@/types';

type Props = {
    profile: BusinessProfile;
    states: IndianStateOption[];
    msmeCategories: { value: MsmeCategoryValue; label: string }[];
    msmeMaxPaymentDays: number;
    changes: BusinessProfileChange[];
};

type TextField = Exclude<
    keyof BusinessProfile,
    | 'show_msme_clause'
    | 'payment_terms_days'
    | 'msme_category'
    | 'logo_url'
    | 'has_udyam_certificate'
>;

const SECTIONS = [
    { id: 'company', label: 'Company' },
    { id: 'gst', label: 'GST' },
    { id: 'udyam', label: 'Udyam / MSME' },
    { id: 'bank', label: 'Bank & UPI' },
    { id: 'invoicing', label: 'Invoicing' },
    { id: 'history', label: 'Change history' },
];

const FIELD_LABELS: Record<string, string> = {
    legal_name: 'Legal name',
    trade_name: 'Trade name',
    address_line: 'Address',
    city: 'City',
    state_code: 'State',
    pincode: 'PIN code',
    email: 'Email',
    phone: 'Phone',
    website: 'Website',
    gstin: 'GSTIN',
    gst_registered_on: 'GST registration date',
    pan: 'PAN',
    udyam_number: 'Udyam number',
    msme_category: 'MSME category',
    msme_category_effective_from: 'MSME category effective from',
    show_msme_clause: 'MSME clause on invoices',
    bank_account_name: 'Account name',
    bank_name: 'Bank',
    bank_branch: 'Branch',
    bank_account_number: 'Account number',
    bank_ifsc: 'IFSC',
    upi_id: 'UPI ID',
    invoice_prefix: 'Invoice prefix',
    payment_terms_days: 'Payment terms',
    invoice_notes: 'Default note',
    invoice_terms: 'Default terms',
    signatory_name: 'Authorised signatory',
    logo_path: 'Logo',
    udyam_certificate_path: 'Udyam certificate',
};

function Section({
    id,
    title,
    description,
    highlighted = false,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    highlighted?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className={cn(
                'scroll-mt-6 rounded-xl border bg-card p-5 shadow-xs sm:p-6',
                highlighted && 'border-primary/60 ring-1 ring-primary/30',
            )}
        >
            <div className="mb-5 space-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <div className="space-y-5">{children}</div>
        </section>
    );
}

export default function EditBusinessProfile({
    profile,
    states,
    msmeCategories,
    msmeMaxPaymentDays,
    changes,
}: Props) {
    const form = useForm({
        legal_name: profile.legal_name ?? '',
        trade_name: profile.trade_name ?? '',
        address_line: profile.address_line ?? '',
        city: profile.city ?? '',
        state_code: profile.state_code ?? '',
        pincode: profile.pincode ?? '',
        email: profile.email ?? '',
        phone: profile.phone ?? '',
        website: profile.website ?? '',
        gstin: profile.gstin ?? '',
        gst_registered_on: profile.gst_registered_on ?? '',
        pan: profile.pan ?? '',
        udyam_number: profile.udyam_number ?? '',
        msme_category: profile.msme_category ?? '',
        msme_category_effective_from:
            profile.msme_category_effective_from ?? '',
        show_msme_clause: profile.show_msme_clause,
        bank_account_name: profile.bank_account_name ?? '',
        bank_name: profile.bank_name ?? '',
        bank_branch: profile.bank_branch ?? '',
        bank_account_number: profile.bank_account_number ?? '',
        bank_ifsc: profile.bank_ifsc ?? '',
        upi_id: profile.upi_id ?? '',
        invoice_prefix: profile.invoice_prefix,
        payment_terms_days: String(profile.payment_terms_days),
        invoice_notes: profile.invoice_notes ?? '',
        invoice_terms: profile.invoice_terms ?? '',
        signatory_name: profile.signatory_name ?? '',
        logo: null as File | null,
        remove_logo: false,
        udyam_certificate: null as File | null,
        remove_udyam_certificate: false,
    });

    const { data, setData, errors, processing, isDirty } = form;
    const [logoPreview, setLogoPreview] = useState<string | null>(
        profile.logo_url,
    );

    const stateCodes = useMemo(() => states.map((s) => s.code), [states]);
    const stateName = (code: string) =>
        states.find((s) => s.code === code)?.name;
    const gstin = checkGstin(data.gstin, stateCodes);
    const gstinChanged = normalizeGstin(data.gstin) !== (profile.gstin ?? '');
    const effectiveStateCode =
        gstin.status === 'valid' ? gstin.stateCode : data.state_code;
    const effectivePan = gstin.status === 'valid' ? gstin.pan : data.pan;

    const text = (field: TextField) => ({
        id: field,
        name: field,
        value: data[field],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => setData(field, event.target.value),
        'aria-invalid': errors[field] ? true : undefined,
    });

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        form.transform((values) => ({ ...values, _method: 'patch' }));
        form.post(BusinessProfileController.update.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () =>
                form.setDefaults({
                    ...data,
                    logo: null,
                    remove_logo: false,
                    udyam_certificate: null,
                    remove_udyam_certificate: false,
                }),
        });
    };

    const discard = () => {
        form.reset();
        form.clearErrors();
        setLogoPreview(profile.logo_url);
    };

    return (
        <>
            <Head title="Business Profile" />

            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 pb-28 sm:p-8 sm:pb-28"
            >
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Business Profile
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        These details print on every invoice. Changes apply to
                        invoices you finalise from now on.
                    </p>
                </div>

                <nav
                    aria-label="Sections"
                    className="-mx-1 flex gap-2 overflow-x-auto px-1"
                >
                    {SECTIONS.map((section) => (
                        <a
                            key={section.id}
                            href={`#${section.id}`}
                            className="shrink-0 rounded-full border bg-card px-3 py-1.5 text-sm text-foreground/80 transition-colors hover:border-primary/40 hover:text-foreground"
                        >
                            {section.label}
                        </a>
                    ))}
                </nav>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Section id="company" title="Company">
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex size-16 items-center justify-center overflow-hidden rounded-xl border border-dashed bg-muted/40 text-xs text-muted-foreground">
                                    {logoPreview ? (
                                        <img
                                            src={logoPreview}
                                            alt="Logo preview"
                                            className="size-full object-contain"
                                        />
                                    ) : (
                                        'Logo'
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <label className="cursor-pointer">
                                            <Upload />
                                            {logoPreview
                                                ? 'Replace logo'
                                                : 'Upload logo'}
                                            <input
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                className="sr-only"
                                                onChange={(event) => {
                                                    const file =
                                                        event.target
                                                            .files?.[0] ?? null;
                                                    setData('logo', file);
                                                    setData(
                                                        'remove_logo',
                                                        false,
                                                    );
                                                    setLogoPreview(
                                                        file
                                                            ? URL.createObjectURL(
                                                                  file,
                                                              )
                                                            : profile.logo_url,
                                                    );
                                                }}
                                            />
                                        </label>
                                    </Button>
                                    {logoPreview && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setData('logo', null);
                                                setData('remove_logo', true);
                                                setLogoPreview(null);
                                            }}
                                        >
                                            <X />
                                            Remove
                                        </Button>
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        PNG, JPG or WebP, square, up to 2 MB
                                    </span>
                                </div>
                                <InputError
                                    className="basis-full"
                                    message={errors.logo}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="legal_name"
                                    label="Legal name"
                                    hint="Exactly as on your GST certificate"
                                    error={errors.legal_name}
                                >
                                    <Input {...text('legal_name')} />
                                </Field>
                                <Field
                                    id="trade_name"
                                    label="Trade name (optional)"
                                    error={errors.trade_name}
                                >
                                    <Input {...text('trade_name')} />
                                </Field>
                                <Field
                                    id="address_line"
                                    label="Address"
                                    error={errors.address_line}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        {...text('address_line')}
                                        autoComplete="street-address"
                                    />
                                </Field>
                                <Field
                                    id="city"
                                    label="City"
                                    error={errors.city}
                                >
                                    <Input
                                        {...text('city')}
                                        autoComplete="address-level2"
                                    />
                                </Field>
                                <div className="grid grid-cols-[minmax(0,1fr)_7rem] gap-4">
                                    <Field
                                        id="state_code"
                                        label="State"
                                        error={errors.state_code}
                                        hint={
                                            gstin.status === 'valid'
                                                ? 'Set by your GSTIN'
                                                : undefined
                                        }
                                    >
                                        <Select
                                            value={effectiveStateCode}
                                            onValueChange={(value) =>
                                                setData('state_code', value)
                                            }
                                            disabled={gstin.status === 'valid'}
                                        >
                                            <SelectTrigger
                                                id="state_code"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select state" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {states.map((state) => (
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
                                    <Field
                                        id="pincode"
                                        label="PIN code"
                                        error={errors.pincode}
                                    >
                                        <Input
                                            {...text('pincode')}
                                            inputMode="numeric"
                                            maxLength={6}
                                            autoComplete="postal-code"
                                        />
                                    </Field>
                                </div>
                                <Field
                                    id="email"
                                    label="Email"
                                    error={errors.email}
                                >
                                    <Input
                                        {...text('email')}
                                        type="email"
                                        autoComplete="email"
                                    />
                                </Field>
                                <Field
                                    id="phone"
                                    label="Phone"
                                    error={errors.phone}
                                >
                                    <Input
                                        {...text('phone')}
                                        type="tel"
                                        autoComplete="tel"
                                    />
                                </Field>
                                <Field
                                    id="website"
                                    label="Website (optional)"
                                    error={errors.website}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        {...text('website')}
                                        type="url"
                                        placeholder="https://"
                                    />
                                </Field>
                            </div>
                        </Section>

                        <Section
                            id="gst"
                            title="GST registration"
                            highlighted={gstinChanged}
                        >
                            <Field
                                id="gstin"
                                label="GSTIN"
                                error={errors.gstin}
                                hint={
                                    gstin.status === 'valid' ? (
                                        <>
                                            Fills in automatically:{' '}
                                            <span className="font-medium text-foreground">
                                                {stateName(gstin.stateCode)} (
                                                {gstin.stateCode})
                                            </span>{' '}
                                            · PAN{' '}
                                            <span className="font-medium text-foreground">
                                                {gstin.pan}
                                            </span>
                                        </>
                                    ) : undefined
                                }
                            >
                                <div className="relative">
                                    <Input
                                        {...text('gstin')}
                                        onChange={(event) =>
                                            setData(
                                                'gstin',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={15}
                                        spellCheck={false}
                                        placeholder="29ABCDE1234F1Z5"
                                        className={cn(
                                            'h-11 pr-36 font-mono text-base tracking-wider',
                                            gstin.status === 'valid' &&
                                                'border-emerald-300 dark:border-emerald-800',
                                        )}
                                    />
                                    <span
                                        aria-live="polite"
                                        className="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1.5 text-xs font-medium"
                                    >
                                        {gstin.status === 'valid' && (
                                            <span className="flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                                                <ShieldCheck className="size-4" />
                                                Valid checksum
                                            </span>
                                        )}
                                        {gstin.status === 'checksum' && (
                                            <span className="text-red-600 dark:text-red-400">
                                                Check character is wrong
                                            </span>
                                        )}
                                        {gstin.status === 'format' && (
                                            <span className="text-red-600 dark:text-red-400">
                                                Not a GSTIN format
                                            </span>
                                        )}
                                        {gstin.status === 'incomplete' && (
                                            <span className="text-muted-foreground">
                                                {
                                                    normalizeGstin(data.gstin)
                                                        .length
                                                }
                                                /15
                                            </span>
                                        )}
                                    </span>
                                </div>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="gst_registered_on"
                                    label="Registration date"
                                    error={errors.gst_registered_on}
                                >
                                    <Input
                                        {...text('gst_registered_on')}
                                        type="date"
                                    />
                                </Field>
                                <Field
                                    id="pan"
                                    label="PAN"
                                    error={errors.pan}
                                    hint={
                                        gstin.status === 'valid'
                                            ? 'Set by your GSTIN'
                                            : undefined
                                    }
                                >
                                    <Input
                                        {...text('pan')}
                                        value={effectivePan}
                                        disabled={gstin.status === 'valid'}
                                        maxLength={10}
                                        className="font-mono tracking-wider uppercase"
                                    />
                                </Field>
                            </div>

                            {gstinChanged && profile.gstin && (
                                <p className="flex gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    Changing your GSTIN only affects invoices
                                    finalised after you save. Invoices already
                                    issued keep the GSTIN printed on them.
                                </p>
                            )}
                        </Section>

                        <Section id="udyam" title="Udyam / MSME">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="udyam_number"
                                    label="Udyam registration number"
                                    error={errors.udyam_number}
                                >
                                    <Input
                                        {...text('udyam_number')}
                                        onChange={(event) =>
                                            setData(
                                                'udyam_number',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={19}
                                        spellCheck={false}
                                        placeholder="UDYAM-KA-03-0012345"
                                        className="font-mono tracking-wide"
                                    />
                                </Field>
                                <div className="grid content-start gap-2">
                                    <Label id="msme_category_label">
                                        Category
                                    </Label>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        aria-labelledby="msme_category_label"
                                        value={data.msme_category}
                                        onValueChange={(value) =>
                                            setData(
                                                'msme_category',
                                                value as MsmeCategoryValue | '',
                                            )
                                        }
                                        className="w-full"
                                    >
                                        {msmeCategories.map((category) => (
                                            <ToggleGroupItem
                                                key={category.value}
                                                value={category.value}
                                                className="flex-1 data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground"
                                            >
                                                {category.label}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                    <InputError
                                        message={errors.msme_category}
                                    />
                                </div>
                                <Field
                                    id="msme_category_effective_from"
                                    label="Category effective from"
                                    hint="The category can change on annual reclassification"
                                    error={errors.msme_category_effective_from}
                                >
                                    <Input
                                        {...text(
                                            'msme_category_effective_from',
                                        )}
                                        type="date"
                                    />
                                </Field>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-dashed p-3">
                                <FileText className="size-8 shrink-0 text-muted-foreground" />
                                <div className="min-w-48 flex-1">
                                    <p className="text-sm font-medium">
                                        Udyam certificate
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {data.udyam_certificate
                                            ? data.udyam_certificate.name
                                            : profile.has_udyam_certificate &&
                                                !data.remove_udyam_certificate
                                              ? 'Uploaded · kept privately for your records'
                                              : 'Not uploaded · optional, PDF or image up to 5 MB'}
                                    </p>
                                </div>
                                {profile.has_udyam_certificate &&
                                    !data.remove_udyam_certificate &&
                                    !data.udyam_certificate && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={BusinessProfileController.certificate.url()}
                                            >
                                                <Download />
                                                Download
                                            </a>
                                        </Button>
                                    )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <label className="cursor-pointer">
                                        <Upload />
                                        {profile.has_udyam_certificate
                                            ? 'Replace'
                                            : 'Upload'}
                                        <input
                                            type="file"
                                            accept="application/pdf,image/png,image/jpeg"
                                            className="sr-only"
                                            onChange={(event) => {
                                                setData(
                                                    'udyam_certificate',
                                                    event.target.files?.[0] ??
                                                        null,
                                                );
                                                setData(
                                                    'remove_udyam_certificate',
                                                    false,
                                                );
                                            }}
                                        />
                                    </label>
                                </Button>
                                <InputError
                                    className="basis-full"
                                    message={errors.udyam_certificate}
                                />
                            </div>

                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="show_msme_clause"
                                    checked={data.show_msme_clause}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'show_msme_clause',
                                            checked === true,
                                        )
                                    }
                                    className="mt-0.5"
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="show_msme_clause">
                                        Print the MSME 45-day payment clause on
                                        invoices
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Also caps payment terms at{' '}
                                        {msmeMaxPaymentDays} days and flags late
                                        invoices on your dashboard.
                                    </p>
                                </div>
                            </div>
                        </Section>

                        <Section
                            id="bank"
                            title="Bank & UPI"
                            description="Printed on invoices so clients can pay you. The UPI ID also becomes a QR code."
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="bank_account_name"
                                    label="Account name"
                                    error={errors.bank_account_name}
                                >
                                    <Input {...text('bank_account_name')} />
                                </Field>
                                <Field
                                    id="bank_account_number"
                                    label="Account number"
                                    error={errors.bank_account_number}
                                >
                                    <Input
                                        {...text('bank_account_number')}
                                        inputMode="numeric"
                                        className="font-mono"
                                    />
                                </Field>
                                <Field
                                    id="bank_ifsc"
                                    label="IFSC"
                                    error={errors.bank_ifsc}
                                >
                                    <Input
                                        {...text('bank_ifsc')}
                                        onChange={(event) =>
                                            setData(
                                                'bank_ifsc',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={11}
                                        placeholder="HDFC0001234"
                                        className="font-mono"
                                    />
                                </Field>
                                <Field
                                    id="upi_id"
                                    label="UPI ID"
                                    error={errors.upi_id}
                                >
                                    <Input
                                        {...text('upi_id')}
                                        placeholder="name@bank"
                                    />
                                </Field>
                                <Field
                                    id="bank_name"
                                    label="Bank"
                                    error={errors.bank_name}
                                >
                                    <Input {...text('bank_name')} />
                                </Field>
                                <Field
                                    id="bank_branch"
                                    label="Branch"
                                    error={errors.bank_branch}
                                >
                                    <Input {...text('bank_branch')} />
                                </Field>
                            </div>
                        </Section>

                        <Section id="invoicing" title="Invoicing defaults">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="invoice_prefix"
                                    label="Invoice number prefix"
                                    hint={`Numbers look like ${data.invoice_prefix || 'INV'}/2026-27/0001`}
                                    error={errors.invoice_prefix}
                                >
                                    <Input
                                        {...text('invoice_prefix')}
                                        maxLength={10}
                                        className="font-mono uppercase"
                                    />
                                </Field>
                                <Field
                                    id="payment_terms_days"
                                    label="Payment terms (days)"
                                    hint={
                                        data.show_msme_clause
                                            ? `Up to ${msmeMaxPaymentDays} days while the MSME clause is on`
                                            : undefined
                                    }
                                    error={errors.payment_terms_days}
                                >
                                    <Input
                                        id="payment_terms_days"
                                        name="payment_terms_days"
                                        type="number"
                                        min={0}
                                        max={
                                            data.show_msme_clause
                                                ? msmeMaxPaymentDays
                                                : 365
                                        }
                                        value={data.payment_terms_days}
                                        onChange={(event) =>
                                            setData(
                                                'payment_terms_days',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            errors.payment_terms_days
                                                ? true
                                                : undefined
                                        }
                                    />
                                </Field>
                                <Field
                                    id="signatory_name"
                                    label="Authorised signatory"
                                    error={errors.signatory_name}
                                    className="sm:col-span-2"
                                >
                                    <Input {...text('signatory_name')} />
                                </Field>
                                <Field
                                    id="invoice_notes"
                                    label="Default note to client"
                                    error={errors.invoice_notes}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        {...text('invoice_notes')}
                                        rows={2}
                                    />
                                </Field>
                                <Field
                                    id="invoice_terms"
                                    label="Default terms"
                                    hint="The MSME clause is added automatically when it is switched on"
                                    error={errors.invoice_terms}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        {...text('invoice_terms')}
                                        rows={3}
                                    />
                                </Field>
                            </div>
                        </Section>
                    </div>

                    <aside className="flex flex-col gap-6 lg:sticky lg:top-6">
                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-3 text-base font-semibold">
                                How it prints
                            </h2>
                            <div className="flex gap-3 rounded-lg border bg-background p-4 text-xs leading-relaxed">
                                <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed">
                                    {logoPreview && (
                                        <img
                                            src={logoPreview}
                                            alt=""
                                            className="size-full object-contain"
                                        />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold">
                                        {data.legal_name || '[Legal name]'}
                                    </p>
                                    <p className="text-muted-foreground">
                                        GSTIN{' '}
                                        <span className="font-mono font-semibold text-foreground">
                                            {normalizeGstin(data.gstin) || '—'}
                                        </span>
                                        {effectivePan && (
                                            <> · PAN {effectivePan}</>
                                        )}
                                    </p>
                                    {data.udyam_number && (
                                        <p className="text-muted-foreground">
                                            Udyam{' '}
                                            <span className="font-mono font-semibold text-foreground">
                                                {data.udyam_number}
                                            </span>
                                            {data.msme_category && (
                                                <>
                                                    {' '}
                                                    ·{' '}
                                                    {
                                                        msmeCategories.find(
                                                            (c) =>
                                                                c.value ===
                                                                data.msme_category,
                                                        )?.label
                                                    }
                                                </>
                                            )}
                                        </p>
                                    )}
                                </div>
                                <span className="shrink-0 text-[11px] font-bold tracking-wide text-primary">
                                    TAX INVOICE
                                </span>
                            </div>
                        </section>

                        <section
                            id="history"
                            className="scroll-mt-6 rounded-xl border bg-card p-5 shadow-xs"
                        >
                            <h2 className="mb-3 flex items-center gap-2 text-base font-semibold">
                                <History className="size-4 text-muted-foreground" />
                                Change history
                            </h2>
                            {changes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Every change you save is recorded here.
                                </p>
                            ) : (
                                <ol className="space-y-3 text-sm">
                                    {changes.map((change) => (
                                        <li
                                            key={change.id}
                                            className="grid gap-0.5"
                                        >
                                            <span className="font-medium">
                                                {FIELD_LABELS[change.field] ??
                                                    change.field}
                                                {change.new_value === null
                                                    ? ' removed'
                                                    : change.old_value === null
                                                      ? ' added'
                                                      : ' changed'}
                                            </span>
                                            {change.old_value &&
                                                change.new_value &&
                                                !change.field.endsWith(
                                                    '_path',
                                                ) && (
                                                    <span className="truncate font-mono text-xs text-muted-foreground">
                                                        {change.old_value} →{' '}
                                                        {change.new_value}
                                                    </span>
                                                )}
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(
                                                    change.created_at,
                                                ).toLocaleString('en-IN', {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}
                                                {change.user &&
                                                    ` · ${change.user}`}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </aside>
                </div>

                {isDirty && (
                    <div className="fixed inset-x-4 bottom-4 z-20 mx-auto flex max-w-3xl flex-wrap items-center gap-3 rounded-xl bg-zinc-900 px-4 py-3 text-white shadow-2xl md:left-[calc(var(--sidebar-width)+1rem)] dark:bg-zinc-800">
                        <span className="min-w-48 flex-1 text-sm">
                            {gstinChanged
                                ? 'GSTIN changed. Save to use it on new invoices.'
                                : 'You have unsaved changes.'}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-white hover:bg-white/10 hover:text-white"
                            onClick={discard}
                        >
                            Discard
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-white text-zinc-900 hover:bg-zinc-100"
                        >
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                )}
            </form>
        </>
    );
}

EditBusinessProfile.layout = {
    breadcrumbs: [
        {
            title: 'Business Profile',
            href: edit(),
        },
    ],
};
