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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('remindable');
            $table->string('type');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('due_on');
            $table->timestamp('completed_at')->nullable();
            $table->string('dedupe_key')->nullable()->unique()->comment('Stops scheduled checks creating the same reminder twice');
            $table->timestamps();

            $table->index(['completed_at', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
