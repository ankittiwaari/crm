<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Invoices are usually built through SaveDraftInvoice so their totals are calculated;
 * this factory makes the bare record for tests that only need an invoice to exist.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state: an unpaid ₹63,720 invoice due in 30 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'status' => InvoiceStatus::Issued,
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'tax_type' => TaxType::IntraState,
            'place_of_supply' => '29',
            'subtotal' => 5_400_000,
            'discount_total' => 0,
            'taxable_total' => 5_400_000,
            'cgst_total' => 486_000,
            'sgst_total' => 486_000,
            'igst_total' => 0,
            'round_off' => 0,
            'total' => 6_372_000,
            'amount_settled' => 0,
            'finalised_at' => now(),
        ];
    }

    public function overdueBy(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'issue_date' => today()->subDays($days + 30),
            'due_date' => today()->subDays($days),
        ]);
    }
}
