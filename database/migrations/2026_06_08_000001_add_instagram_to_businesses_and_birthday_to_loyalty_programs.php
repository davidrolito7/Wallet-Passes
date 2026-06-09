<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('instagram_url')->nullable()->after('contact_phone');
        });

        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->boolean('birthday_reward_enabled')->default(false)->after('google_wallet_notification_mode');
            $table->string('birthday_reward_title')->nullable()->after('birthday_reward_enabled');
            $table->text('birthday_reward_description')->nullable()->after('birthday_reward_title');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('instagram_url');
        });

        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn(['birthday_reward_enabled', 'birthday_reward_title', 'birthday_reward_description']);
        });
    }
};
