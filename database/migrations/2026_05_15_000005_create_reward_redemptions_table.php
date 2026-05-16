<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->string('reward_title');
            $table->string('redeemed_by')->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['loyalty_card_id', 'redeemed_at']);
        });
    }
};
