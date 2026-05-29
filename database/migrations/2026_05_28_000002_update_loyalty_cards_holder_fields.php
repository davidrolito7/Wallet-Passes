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
            $table->string('first_name')->nullable()->after('loyalty_program_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('last_name');
        });

        // Migrar datos existentes: holder_name → first_name
        DB::table('loyalty_cards')->whereNotNull('holder_name')->update([
            'first_name' => DB::raw('holder_name'),
        ]);

        Schema::table('loyalty_cards', function (Blueprint $table) {
            $table->dropIndex(['loyalty_program_id', 'holder_email']);
            $table->dropColumn(['holder_name', 'holder_email']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_cards', function (Blueprint $table) {
            $table->string('holder_name')->nullable()->after('loyalty_program_id');
            $table->string('holder_email')->nullable()->after('holder_name');
        });

        DB::table('loyalty_cards')->whereNotNull('first_name')->update([
            'holder_name' => DB::raw("CONCAT(first_name, ' ', COALESCE(last_name, ''))"),
        ]);

        Schema::table('loyalty_cards', function (Blueprint $table) {
            $table->unique(['loyalty_program_id', 'holder_email']);
            $table->dropColumn(['first_name', 'last_name', 'birth_date']);
        });
    }
};
