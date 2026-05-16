<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('total_stamps')->default(10);
            $table->string('stamp_icon')->default('coffee'); // coffee|star|stamp|heart|custom
            $table->string('stamp_icon_url')->nullable();     // for custom icon
            $table->string('reward_title');
            $table->text('reward_description')->nullable();
            $table->string('google_class_suffix')->unique()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
