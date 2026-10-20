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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->string('sac_code', 8)->default('998314');
            $table->decimal('tax_rate', 5, 2)->default(18);
            $table->unsignedBigInteger('list_price')->nullable()->comment('Indicative price in paise; deals are negotiated');
            $table->string('default_billing_cycle')->default('yearly');
            $table->string('unit_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
