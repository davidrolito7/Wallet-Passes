<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('website');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_relevant_text')->nullable()->after('longitude');
            $table->unsignedInteger('location_radius_meters')->nullable()->default(150)->after('location_relevant_text');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_relevant_text', 'location_radius_meters']);
        });
    }
};
