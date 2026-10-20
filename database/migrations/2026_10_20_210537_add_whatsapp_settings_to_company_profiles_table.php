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
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_waba_id')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_display_number')->nullable();
            $table->text('whatsapp_access_token')->nullable()->comment('Encrypted');
            $table->text('whatsapp_app_secret')->nullable()->comment('Encrypted');
            $table->string('whatsapp_verify_token')->nullable();
            $table->json('whatsapp_templates')->nullable()->comment('Template name and language per message type');
            $table->json('whatsapp_template_statuses')->nullable()->comment('Approval status per template name, from Meta');
            $table->timestamp('whatsapp_templates_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_enabled', 'whatsapp_waba_id', 'whatsapp_phone_number_id', 'whatsapp_display_number',
                'whatsapp_access_token', 'whatsapp_app_secret', 'whatsapp_verify_token', 'whatsapp_templates',
                'whatsapp_template_statuses', 'whatsapp_templates_synced_at',
            ]);
        });
    }
};
