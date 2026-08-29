<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('relevant_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cada negocio con ubicación única ya configurada pasa a tener una fila en la
        // tabla nueva, para no perder lo que ya tenían guardado (Apple/Google Wallet).
        DB::table('businesses')
            ->where('location_enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude', 'location_relevant_text')
            ->orderBy('id')
            ->each(function ($business) {
                DB::table('business_locations')->insert([
                    'business_id'   => $business->id,
                    'name'          => $business->name,
                    'latitude'      => $business->latitude,
                    'longitude'     => $business->longitude,
                    'relevant_text' => $business->location_relevant_text,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_enabled', 'location_relevant_text']);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('website');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('location_enabled')->default(false)->after('longitude');
            $table->string('location_relevant_text')->nullable()->after('location_enabled');
        });

        // Solo se puede restaurar una ubicación por negocio en las columnas antiguas;
        // se toma la primera activa de cada negocio.
        DB::table('business_locations')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->unique('business_id')
            ->each(function ($location) {
                DB::table('businesses')->where('id', $location->business_id)->update([
                    'latitude'               => $location->latitude,
                    'longitude'              => $location->longitude,
                    'location_enabled'       => true,
                    'location_relevant_text' => $location->relevant_text,
                ]);
            });

        Schema::dropIfExists('business_locations');
    }
};
