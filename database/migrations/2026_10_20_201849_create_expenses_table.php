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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->string('category');
            $table->string('vendor')->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount')->comment('Paise actually paid, including any GST');
            $table->unsignedBigInteger('gst_amount')->default(0)->comment('Paise of input GST you can claim back');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['spent_on', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
