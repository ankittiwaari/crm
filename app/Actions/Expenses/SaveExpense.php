<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaveExpense
{
    /**
     * Create or update an expense, storing a new receipt privately and deleting a replaced one.
     *
     * @param  array<string, mixed>  $attributes  Validated fields with amounts in paise.
     */
    public function handle(array $attributes, ?UploadedFile $receipt = null, bool $removeReceipt = false, ?Expense $expense = null, ?User $user = null): Expense
    {
        $expense ??= new Expense(['user_id' => $user?->id]);
        $expense->fill($attributes);

        if ($removeReceipt || $receipt !== null) {
            $this->deleteReceipt($expense);
        }

        if ($receipt !== null) {
            $path = $receipt->store('receipts', 'local');

            if ($path === false) {
                throw new RuntimeException('Could not store the receipt.');
            }

            $expense->receipt_path = $path;
        }

        $expense->save();

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $this->deleteReceipt($expense);
        $expense->delete();
    }

    private function deleteReceipt(Expense $expense): void
    {
        if ($expense->receipt_path !== null) {
            Storage::disk('local')->delete($expense->receipt_path);
            $expense->receipt_path = null;
        }
    }
}
