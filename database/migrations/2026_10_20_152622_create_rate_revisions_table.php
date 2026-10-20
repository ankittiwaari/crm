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
        Schema::create('rate_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('old_pricing_model')->nullable();
            $table->string('new_pricing_model');
            $table->unsignedBigInteger('old_rate')->nullable();
            $table->unsignedBigInteger('new_rate');
            $table->unsignedInteger('old_quantity')->nullable();
            $table->unsignedInteger('new_quantity');
            $table->date('effective_from');
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_revisions');
    }
};
