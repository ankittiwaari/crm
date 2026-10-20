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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();

            $table->char('gstin', 15)->nullable();
            $table->date('gst_registered_on')->nullable();
            $table->char('pan', 10)->nullable();

            $table->string('udyam_number', 19)->nullable();
            $table->string('msme_category')->nullable();
            $table->date('msme_category_effective_from')->nullable();
            $table->string('udyam_certificate_path')->nullable();
            $table->boolean('show_msme_clause')->default(true);

            $table->string('bank_account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_number', 34)->nullable();
            $table->char('bank_ifsc', 11)->nullable();
            $table->string('upi_id')->nullable();

            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('invoice_notes')->nullable();
            $table->text('invoice_terms')->nullable();
            $table->string('signatory_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
