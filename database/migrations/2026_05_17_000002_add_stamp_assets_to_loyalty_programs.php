<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->string('stamp_style', 30)->default('minimal')->after('card_font');
            $table->string('filled_stamp_image', 500)->nullable()->after('stamp_style');
            $table->string('empty_stamp_image', 500)->nullable()->after('filled_stamp_image');
            $table->string('reward_badge_image', 500)->nullable()->after('empty_stamp_image');
            $table->decimal('stamp_scale', 3, 2)->default(1.00)->after('reward_badge_image');
            $table->tinyInteger('stamp_spacing')->default(15)->after('stamp_scale');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn([
                'stamp_style', 'filled_stamp_image', 'empty_stamp_image',
                'reward_badge_image', 'stamp_scale', 'stamp_spacing',
            ]);
        });
    }
};
