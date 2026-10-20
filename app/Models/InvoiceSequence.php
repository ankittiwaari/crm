<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The last invoice number used in a financial year.
 *
 * @property int $id
 * @property string $financial_year
 * @property int $last_number
 */
#[Fillable(['financial_year', 'last_number'])]
class InvoiceSequence extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
