<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
            $table->string('holder_name');
            $table->string('holder_email')->nullable();
            $table->string('holder_identifier')->nullable(); // device/customer identifier
            $table->integer('stamps_collected')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_stamp_at')->nullable();
            $table->uuid('google_pass_id')->nullable();
            $table->uuid('apple_pass_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['loyalty_program_id', 'holder_email']);
            $table->index(['loyalty_program_id', 'holder_identifier']);
        });
    }
};
