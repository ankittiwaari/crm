export type Option<T extends string = string> = {
    value: T;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type ClientTypeValue =
    | 'school'
    | 'pharmacy'
    | 'clinic'
    | 'retail'
    | 'other';
export type ClientStatusValue = 'lead' | 'active' | 'inactive' | 'churned';
export type ActivityTypeValue =
    | 'call'
    | 'meeting'
    | 'demo'
    | 'email'
    | 'whatsapp'
    | 'note';
export type BillingCycleValue =
    | 'one_time'
    | 'monthly'
    | 'quarterly'
    | 'half_yearly'
    | 'yearly';

export type Contact = {
    id: number;
    name: string;
    designation: string | null;
    email: string | null;
    phone: string | null;
    whatsapp_number: string | null;
    whatsapp_target: string | null;
    whatsapp_opted_in: boolean;
    whatsapp_opted_in_at: string | null;
    is_primary: boolean;
    receives_invoices: boolean;
};

export type Client = {
    id: number;
    name: string;
    type: ClientTypeValue;
    type_label: string;
    status: ClientStatusValue;
    status_label: string;
    source: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    billing_address: string | null;
    city: string | null;
    state_code: string | null;
    state_name: string | null;
    pincode: string | null;
    gstin: string | null;
    notes: string | null;
    whatsapp_automation: boolean;
    last_activity_at: string | null;
    created_at: string | null;
    primary_contact?: Contact | null;
    contacts?: Contact[];
    annual_value?: number;
    next_renewal_on?: string | null;
};

export type Activity = {
    id: number;
    type: ActivityTypeValue;
    type_label: string;
    body: string;
    happened_at: string;
    follow_up_on: string | null;
    user?: string | null;
};

export type Product = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    sac_code: string;
    tax_rate: string;
    list_price: number | null;
    list_price_rupees: string | null;
    default_billing_cycle: BillingCycleValue;
    default_billing_cycle_label: string;
    unit_label: string | null;
    is_active: boolean;
    usage?: ProductUsage;
};

export type ClientOptions = {
    types: Option<ClientTypeValue>[];
    statuses: Option<ClientStatusValue>[];
    states: { code: string; name: string }[];
};

export type SubscriptionStatusValue =
    | 'trial'
    | 'active'
    | 'suspended'
    | 'expired'
    | 'cancelled';
export type PricingModelValue = 'flat' | 'per_unit';

export type RateRevision = {
    id: number;
    old_amount: number | null;
    new_amount: number;
    new_pricing_model: PricingModelValue;
    new_rate: number;
    new_quantity: number;
    effective_from: string;
    reason: string;
};

export type Subscription = {
    id: number;
    client_id: number;
    product_id: number;
    product_name?: string;
    product_unit_label?: string | null;
    status: SubscriptionStatusValue;
    status_label: string;
    is_live: boolean;
    billing_cycle: BillingCycleValue;
    billing_cycle_label: string;
    billing_cycle_short: string;
    pricing_model: PricingModelValue;
    rate: number;
    rate_rupees: string;
    quantity: number;
    unit_count: number | null;
    amount_per_cycle: number;
    annual_value: number;
    setup_fee: number | null;
    setup_fee_rupees: string | null;
    is_promotional: boolean;
    promo_ends_on: string | null;
    promo_note: string | null;
    start_date: string;
    current_period_start: string | null;
    current_period_end: string | null;
    days_until_renewal: number | null;
    renewal_state: 'current' | 'in_grace' | 'lapsed' | null;
    grace_ends_on: string | null;
    next_period: { start: string; end: string } | null;
    periods?: SubscriptionPeriodRow[];
    auto_renew: boolean;
    renewal_notice_days: number;
    grace_days: number;
    notes: string | null;
    rate_revisions?: RateRevision[];
};

export type ProductOption = {
    id: number;
    name: string;
    list_price_rupees: string | null;
    default_billing_cycle: BillingCycleValue;
    unit_label: string | null;
};

export type SubscriptionOptions = {
    subscriptionStatuses: Option<SubscriptionStatusValue>[];
    billingCycles: Option<BillingCycleValue>[];
    pricingModels: Option<PricingModelValue>[];
};

export type ProductUsage = {
    clients: number;
    annual_value: number;
    min_annual: number | null;
    max_annual: number | null;
};

export type InvoiceStatusValue =
    | 'draft'
    | 'issued'
    | 'partially_paid'
    | 'paid'
    | 'cancelled';

export type InvoiceLine = {
    subscription_id: number | null;
    product_id: number | null;
    description: string;
    sac_code: string;
    period_start: string;
    period_end: string;
    quantity: string;
    unit_price: string;
    discount: string;
    tax_rate: string;
    key?: string;
    is_renewal?: boolean;
};

export type InvoiceItem = {
    id: number;
    subscription_id: number | null;
    product_id: number | null;
    description: string;
    sac_code: string;
    period_start: string | null;
    period_end: string | null;
    quantity: number;
    unit_price: number;
    discount: number;
    tax_rate: string;
    taxable_amount: number;
    line_total: number;
};

export type Payment = {
    id: number;
    amount: number;
    tds_amount: number;
    paid_on: string;
    method: string;
    method_label: string;
    reference: string | null;
    notes: string | null;
};

export type InvoiceMessage = {
    id: number;
    channel: 'whatsapp_link' | 'whatsapp_api' | 'email';
    channel_label: string;
    direction: 'outbound' | 'inbound';
    status:
        | 'queued'
        | 'sent'
        | 'delivered'
        | 'read'
        | 'failed'
        | 'received'
        | null;
    error: string | null;
    recipient: string;
    sent_at: string;
};

export type Invoice = {
    id: number;
    number: string | null;
    display_number: string;
    status: InvoiceStatusValue;
    status_label: string;
    is_overdue: boolean;
    days_overdue: number;
    client_id: number;
    client_name: string;
    issue_date: string;
    due_date: string;
    tax_type: 'intra_state' | 'inter_state';
    tax_type_label: string;
    place_of_supply: string | null;
    subtotal: number;
    taxable_total: number;
    cgst_total: number;
    sgst_total: number;
    igst_total: number;
    round_off: number;
    total: number;
    amount_settled: number;
    balance_due: number;
    notes: string | null;
    terms: string | null;
    finalised_at: string | null;
    paid_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    items?: InvoiceItem[];
    payments?: Payment[];
    messages?: InvoiceMessage[];
};

export type ReminderTypeValue =
    | 'renewal'
    | 'promo_review'
    | 'payment_follow_up'
    | 'follow_up'
    | 'general';

export type Reminder = {
    id: number;
    type: ReminderTypeValue;
    type_label: string;
    title: string;
    notes: string | null;
    due_on: string;
    is_overdue: boolean;
    completed_at: string | null;
    client_id: number | null;
    client_name?: string | null;
    link: string | null;
};

export type SubscriptionPeriodRow = {
    id: number;
    period_start: string;
    period_end: string;
    status: 'pending_payment' | 'paid' | 'overridden' | 'waived';
    status_label: string;
    invoice_id: number | null;
    override_reason: string | null;
    can_undo: boolean;
};

export type Expense = {
    id: number;
    spent_on: string;
    category: string;
    category_label: string;
    vendor: string | null;
    description: string;
    amount: number;
    amount_rupees: string;
    gst_amount: number;
    gst_amount_rupees: string;
    net_cost: number;
    payment_method: string | null;
    reference: string | null;
    is_recurring: boolean;
    has_receipt: boolean;
    notes: string | null;
};
