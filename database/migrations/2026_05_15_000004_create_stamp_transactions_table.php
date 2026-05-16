<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->integer('stamps_added')->default(1);
            $table->integer('stamps_after')->default(0);
            $table->string('note')->nullable();
            $table->string('recorded_by')->nullable(); // admin user or system
            $table->timestamps();

            $table->index(['loyalty_card_id', 'created_at']);
        });
    }
};
