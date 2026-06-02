<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            // Per-visit notification message shown in Google Wallet when a stamp is added.
            $table->boolean('visit_notification_enabled')->default(false)->after('reward_description');
            $table->string('visit_notification_title')->nullable()->after('visit_notification_enabled');
            $table->text('visit_notification_message')->nullable()->after('visit_notification_title');

            // Controls how Google Wallet notifies the user on each stamp update.
            // balance_update_only  — PATCH with notifyPreference only (system notification).
            // custom_message_only  — PATCH silently + addMessage TEXT_AND_NOTIFY.
            // both                 — PATCH with notifyPreference + addMessage (two notifications).
            $table->string('google_wallet_notification_mode')->default('balance_update_only')->after('visit_notification_message');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn([
                'visit_notification_enabled',
                'visit_notification_title',
                'visit_notification_message',
                'google_wallet_notification_mode',
            ]);
        });
    }
};
