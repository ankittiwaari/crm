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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('billing_cycle');
            $table->string('pricing_model')->default('flat');
            $table->unsignedBigInteger('rate')->comment('Paise per cycle (flat) or per unit per cycle');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_count')->nullable()->comment('e.g. students on record, even on flat deals');
            $table->unsignedBigInteger('setup_fee')->nullable()->comment('Paise, one-time');
            $table->boolean('is_promotional')->default(false);
            $table->date('promo_ends_on')->nullable();
            $table->string('promo_note')->nullable();
            $table->date('start_date');
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable()->comment('Next renewal date; null for one-time');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renewal_notice_days')->default(30);
            $table->unsignedSmallInteger('grace_days')->default(15);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'current_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
