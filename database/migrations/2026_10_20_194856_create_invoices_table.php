<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique()->comment('Assigned when finalised');
            $table->string('financial_year', 7)->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->char('place_of_supply', 2)->nullable();
            $table->string('tax_type')->default('intra_state');
            $table->json('client_snapshot')->nullable();
            $table->json('seller_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('taxable_total')->default(0);
            $table->unsignedBigInteger('cgst_total')->default(0);
            $table->unsignedBigInteger('sgst_total')->default(0);
            $table->unsignedBigInteger('igst_total')->default(0);
            $table->integer('round_off')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_settled')->default(0)->comment('Payments received plus TDS deducted');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('finalised_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['financial_year', 'sequence']);
            $table->index(['status', 'due_date']);
            $table->index(['client_id', 'issue_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
