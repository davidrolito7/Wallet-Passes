<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stamp_count');  // trigger at this stamp count
            $table->string('reward_title');
            $table->text('reward_description')->nullable();
            $table->boolean('is_repeatable')->default(false); // repeats each cycle
            $table->timestamps();

            $table->unique(['loyalty_program_id', 'stamp_count']);
        });

        Schema::create('milestone_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_milestone_id')->constrained()->cascadeOnDelete();
            $table->string('redeemed_by')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['loyalty_card_id', 'loyalty_milestone_id']);
        });
    }
};
