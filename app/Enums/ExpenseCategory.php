<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExpenseCategory: string
{
    use HasOptions;

    case Hosting = 'hosting';
    case Domains = 'domains';
    case Software = 'software';
    case Hardware = 'hardware';
    case Marketing = 'marketing';
    case Travel = 'travel';
    case Salaries = 'salaries';
    case Office = 'office';
    case ProfessionalFees = 'professional_fees';
    case BankCharges = 'bank_charges';
    case Taxes = 'taxes';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting & servers',
            self::Domains => 'Domains & SSL',
            self::Software => 'Software & subscriptions',
            self::Hardware => 'Hardware',
            self::Marketing => 'Marketing',
            self::Travel => 'Travel',
            self::Salaries => 'Salaries & contractors',
            self::Office => 'Office & utilities',
            self::ProfessionalFees => 'CA & professional fees',
            self::BankCharges => 'Bank & payment charges',
            self::Taxes => 'Taxes & government fees',
            self::Other => 'Other',
        };
    }
}
