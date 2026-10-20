<?php

namespace App\Services\Invoices;

use App\Enums\TaxType;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Support\Gstin;

/**
 * Seller and client details as printed on an invoice. They are frozen into the invoice when it is
 * finalised, so later edits to your profile or the client never change an issued invoice.
 */
class InvoiceParties
{
    /**
     * @return array<string, mixed>
     */
    public function seller(CompanyProfile $profile): array
    {
        return [
            'legal_name' => $profile->legal_name,
            'trade_name' => $profile->trade_name,
            'address_line' => $profile->address_line,
            'city' => $profile->city,
            'state_code' => $profile->state_code,
            'state_name' => $profile->state()?->label(),
            'pincode' => $profile->pincode,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'gstin' => $profile->gstin,
            'pan' => $profile->pan,
            'udyam_number' => $profile->udyam_number,
            'msme_category' => $profile->msme_category?->label(),
            'show_msme_clause' => $profile->show_msme_clause,
            'bank_account_name' => $profile->bank_account_name ?: $profile->legal_name,
            'bank_name' => $profile->bank_name,
            'bank_branch' => $profile->bank_branch,
            'bank_account_number' => $profile->bank_account_number,
            'bank_ifsc' => $profile->bank_ifsc,
            'upi_id' => $profile->upi_id,
            'signatory_name' => $profile->signatory_name,
            'logo_path' => $profile->logo_path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function client(Client $client): array
    {
        $contact = $client->billingContact();

        return [
            'name' => $client->name,
            'billing_address' => $client->billing_address,
            'city' => $client->city,
            'state_code' => $client->state_code,
            'state_name' => $client->state()?->label(),
            'pincode' => $client->pincode,
            'gstin' => $client->gstin,
            'email' => $client->email,
            'attention' => $contact
                ? trim($contact->name.($contact->designation ? ', '.$contact->designation : ''))
                : null,
        ];
    }

    /**
     * Place of supply: the client's state (from their GSTIN when registered), else your own state.
     */
    public function placeOfSupply(Client $client, CompanyProfile $profile): ?string
    {
        return Gstin::tryFrom($client->gstin)?->state()->value
            ?? $client->state_code
            ?? $profile->state_code;
    }

    public function taxType(?string $placeOfSupply, CompanyProfile $profile): TaxType
    {
        return $placeOfSupply === null || $profile->state_code === null || $placeOfSupply === $profile->state_code
            ? TaxType::IntraState
            : TaxType::InterState;
    }
}
