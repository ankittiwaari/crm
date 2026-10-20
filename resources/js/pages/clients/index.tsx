import { Head, Link, router } from '@inertiajs/react';
import { Phone, Plus, Search, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { index, show } from '@/actions/App/Http/Controllers/ClientController';
import { ClientForm } from '@/components/crm/client-form';
import { Pagination } from '@/components/crm/pagination';
import { ClientStatusBadge } from '@/components/crm/status-badge';
import { WhatsAppButton } from '@/components/crm/whatsapp-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import {
    formatDate,
    formatInr,
    formatPhone,
    initials,
    timeAgo,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    Client,
    ClientOptions,
    ClientStatusValue,
    ClientTypeValue,
    Paginated,
} from '@/types';

type Filters = {
    search: string;
    status: ClientStatusValue | null;
    type: ClientTypeValue | null;
    sort: 'name' | 'recent';
};

type Props = {
    clients: Paginated<Client>;
    filters: Filters;
    statusCounts: Record<'all' | ClientStatusValue, number>;
    options: ClientOptions;
};

function RenewalCell({ date }: { date: string | null }) {
    if (!date) {
        return <span className="text-muted-foreground">—</span>;
    }

    const days = Math.round(
        (new Date(date).getTime() - new Date().setHours(0, 0, 0, 0)) /
            86_400_000,
    );

    return (
        <span className="grid gap-0.5">
            <span>{formatDate(date)}</span>
            {days <= 30 && (
                <span
                    className={cn(
                        'w-fit rounded-full px-2 py-0.5 text-[11px] font-semibold',
                        days <= 7
                            ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300'
                            : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
                    )}
                >
                    {days < 0
                        ? `${Math.abs(days)}d overdue`
                        : days === 0
                          ? 'today'
                          : `in ${days} ${days === 1 ? 'day' : 'days'}`}
                </span>
            )}
        </span>
    );
}

export default function ClientsIndex({
    clients,
    filters,
    statusCounts,
    options,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [adding, setAdding] = useState(false);
    const firstRender = useRef(true);

    const applyFilters = (changes: Partial<Filters>) => {
        const next = { ...filters, search, ...changes };

        router.get(
            index.url(),
            {
                search: next.search || undefined,
                status: next.status ?? undefined,
                type: next.type ?? undefined,
                sort: next.sort === 'name' ? undefined : next.sort,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const tabs: { value: ClientStatusValue | null; label: string }[] = [
        { value: null, label: 'All' },
        ...options.statuses,
    ];
    const isFiltered = Boolean(
        filters.search || filters.status || filters.type,
    );

    return (
        <>
            <Head title="Clients" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Clients
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {statusCounts.all}{' '}
                            {statusCounts.all === 1 ? 'client' : 'clients'}
                            {statusCounts.lead > 0 &&
                                ` · ${statusCounts.lead} ${statusCounts.lead === 1 ? 'lead' : 'leads'}`}
                        </p>
                    </div>
                    <Button onClick={() => setAdding(true)}>
                        <Plus />
                        Add client
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div
                        role="tablist"
                        aria-label="Status"
                        className="flex gap-1 overflow-x-auto rounded-lg bg-muted p-1"
                    >
                        {tabs.map((tab) => {
                            const selected = filters.status === tab.value;

                            return (
                                <button
                                    key={tab.label}
                                    type="button"
                                    role="tab"
                                    aria-selected={selected}
                                    onClick={() =>
                                        applyFilters({ status: tab.value })
                                    }
                                    className={cn(
                                        'h-8 shrink-0 rounded-md px-3 text-sm whitespace-nowrap transition-colors',
                                        selected
                                            ? 'bg-card font-medium text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span className="text-xs text-muted-foreground">
                                        {statusCounts[tab.value ?? 'all']}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            aria-label="Search clients"
                            placeholder="Name, city, phone or GSTIN"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="bg-card pl-9"
                        />
                    </div>

                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                type:
                                    value === 'all'
                                        ? null
                                        : (value as ClientTypeValue),
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Client type"
                            className="w-40 bg-card"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {options.types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.sort}
                        onValueChange={(value) =>
                            applyFilters({ sort: value as Filters['sort'] })
                        }
                    >
                        <SelectTrigger
                            aria-label="Sort"
                            className="w-44 bg-card"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="name">Sort by name</SelectItem>
                            <SelectItem value="recent">
                                Recently contacted
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {clients.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card px-6 py-16 text-center">
                        <Users className="size-8 text-muted-foreground" />
                        <p className="font-medium">
                            {isFiltered
                                ? 'No clients match these filters'
                                : 'No clients yet'}
                        </p>
                        {isFiltered ? (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    applyFilters({
                                        search: '',
                                        status: null,
                                        type: null,
                                    });
                                }}
                            >
                                Clear filters
                            </Button>
                        ) : (
                            <Button onClick={() => setAdding(true)}>
                                <Plus />
                                Add your first client
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">
                                            Client
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Main contact
                                        </th>
                                        <th className="px-3 py-3 text-right font-medium">
                                            Yearly value
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Next renewal
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Last contact
                                        </th>
                                        <th className="px-4 py-3">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {clients.data.map((client) => {
                                        const contact = client.primary_contact;
                                        const whatsapp =
                                            contact?.whatsapp_target ??
                                            client.phone;

                                        return (
                                            <tr
                                                key={client.id}
                                                onClick={() =>
                                                    router.visit(
                                                        show.url(client.id),
                                                    )
                                                }
                                                className="cursor-pointer transition-colors hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={show(client.id)}
                                                        prefetch
                                                        className="flex items-center gap-3"
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                    >
                                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground">
                                                            {initials(
                                                                client.name,
                                                            )}
                                                        </span>
                                                        <span className="grid">
                                                            <span className="font-medium">
                                                                {client.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {[
                                                                    client.type_label,
                                                                    client.city,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
                                                            </span>
                                                        </span>
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <ClientStatusBadge
                                                        status={client.status}
                                                        label={
                                                            client.status_label
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3">
                                                    {contact ? (
                                                        <span className="grid">
                                                            <span>
                                                                {contact.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {[
                                                                    contact.designation,
                                                                    formatPhone(
                                                                        contact.phone,
                                                                    ),
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 text-right font-medium">
                                                    {client.annual_value ? (
                                                        formatInr(
                                                            client.annual_value,
                                                        )
                                                    ) : (
                                                        <span className="font-normal text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <RenewalCell
                                                        date={
                                                            client.next_renewal_on ??
                                                            null
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3 text-muted-foreground">
                                                    {client.last_activity_at
                                                        ? timeAgo(
                                                              client.last_activity_at,
                                                          )
                                                        : 'Never'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-2">
                                                        {whatsapp && (
                                                            <WhatsAppButton
                                                                number={
                                                                    whatsapp
                                                                }
                                                                name={
                                                                    contact?.name ??
                                                                    client.name
                                                                }
                                                                iconOnly
                                                            />
                                                        )}
                                                        {(contact?.phone ??
                                                            client.phone) && (
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                                size="icon"
                                                            >
                                                                <a
                                                                    href={`tel:${contact?.phone ?? client.phone}`}
                                                                    aria-label={`Call ${contact?.name ?? client.name}`}
                                                                    onClick={(
                                                                        event,
                                                                    ) =>
                                                                        event.stopPropagation()
                                                                    }
                                                                >
                                                                    <Phone />
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t px-4 py-3">
                            <Pagination page={clients} />
                        </div>
                    </div>
                )}
            </div>

            <Sheet open={adding} onOpenChange={setAdding}>
                <SheetContent className="w-full gap-0 sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Add client</SheetTitle>
                        <SheetDescription>
                            Only the name is required. You can fill in the rest
                            later.
                        </SheetDescription>
                    </SheetHeader>
                    {adding && (
                        <ClientForm
                            options={options}
                            onDone={() => setAdding(false)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [{ title: 'Clients', href: index() }],
};
