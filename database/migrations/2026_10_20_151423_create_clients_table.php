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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('lead');
            $table->string('source')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 16)->nullable();
            $table->string('website')->nullable();
            $table->string('billing_address', 500)->nullable();
            $table->string('city')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->char('gstin', 15)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
