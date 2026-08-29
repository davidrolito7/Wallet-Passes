<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_cards', function (Blueprint $table) {
            // Ancla la ventana de vigencia (validity_months) del ciclo actual. A diferencia
            // de created_at (fecha real de alta, se muestra en "Miembro desde" y nunca cambia),
            // esta se reinicia cada vez que la tarjeta se canjea o vence sin completarse.
            $table->timestamp('cycle_started_at')->nullable()->after('completed_at');
        });

        DB::table('loyalty_cards')->whereNull('cycle_started_at')->update([
            'cycle_started_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('loyalty_cards', function (Blueprint $table) {
            $table->dropColumn('cycle_started_at');
        });
    }
};
