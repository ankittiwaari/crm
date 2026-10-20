export type SharedCompany = {
    name: string | null;
    logo_url: string | null;
};

export type MsmeCategoryValue = 'micro' | 'small' | 'medium';

export type BusinessProfile = {
    legal_name: string | null;
    trade_name: string | null;
    address_line: string | null;
    city: string | null;
    state_code: string | null;
    pincode: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    gstin: string | null;
    gst_registered_on: string | null;
    pan: string | null;
    udyam_number: string | null;
    msme_category: MsmeCategoryValue | null;
    msme_category_effective_from: string | null;
    show_msme_clause: boolean;
    bank_account_name: string | null;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_number: string | null;
    bank_ifsc: string | null;
    upi_id: string | null;
    invoice_prefix: string;
    payment_terms_days: number;
    invoice_notes: string | null;
    invoice_terms: string | null;
    signatory_name: string | null;
    logo_url: string | null;
    has_udyam_certificate: boolean;
};

export type IndianStateOption = {
    code: string;
    name: string;
};

export type BusinessProfileChange = {
    id: number;
    field: string;
    old_value: string | null;
    new_value: string | null;
    user: string | null;
    created_at: string;
};

export type BusinessProfileSummary = {
    name: string | null;
    gstin: string | null;
    state: string | null;
    udyam_number: string | null;
    msme_category: string | null;
    missing: string[];
};
