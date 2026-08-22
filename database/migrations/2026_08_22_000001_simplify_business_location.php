<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Apple ignora maxDistance por encima de su propio tope interno para storeCard
            // (~100 m), así que dejó de tener sentido pedirle un radio al negocio.
            $table->dropColumn('location_radius_meters');

            $table->boolean('location_enabled')->default(false)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('location_enabled');
            $table->unsignedInteger('location_radius_meters')->nullable()->default(150)->after('location_relevant_text');
        });
    }
};
