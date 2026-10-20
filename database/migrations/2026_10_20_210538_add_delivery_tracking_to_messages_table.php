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
        Schema::table('messages', function (Blueprint $table) {
            $table->string('direction')->default('outbound')->after('channel');
            $table->string('status')->nullable()->after('direction')->comment('queued, sent, delivered, read, failed, received');
            $table->string('provider_message_id')->nullable()->index()->after('status');
            $table->json('payload')->nullable()->comment('What was sent to the provider');
            $table->text('error')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('sent_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['direction', 'status', 'provider_message_id', 'payload', 'error', 'status_updated_at']);
        });
    }
};
