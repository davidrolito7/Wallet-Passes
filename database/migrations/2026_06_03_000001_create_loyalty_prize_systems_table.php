<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create prize systems table (skip if partially run before)
        if (! Schema::hasTable('loyalty_prize_systems')) {
            Schema::create('loyalty_prize_systems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->string('reward_title');
                $table->text('reward_description')->nullable();
                $table->timestamps();

                $table->index(['loyalty_program_id', 'sort_order']);
            });
        }

        // 2. Add prize system FK to milestones (nullable during data migration)
        if (! Schema::hasColumn('loyalty_milestones', 'loyalty_prize_system_id')) {
            Schema::table('loyalty_milestones', function (Blueprint $table) {
                $table->foreignId('loyalty_prize_system_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('loyalty_prize_systems')
                    ->cascadeOnDelete();
            });
        }

        // 3. For each program without a prize system yet: create first prize system and link milestones
        $programs = DB::table('loyalty_programs')
            ->select('id', 'reward_title', 'reward_description')
            ->get();

        foreach ($programs as $program) {
            $existing = DB::table('loyalty_prize_systems')
                ->where('loyalty_program_id', $program->id)
                ->where('sort_order', 1)
                ->first();

            $psId = $existing
                ? $existing->id
                : DB::table('loyalty_prize_systems')->insertGetId([
                    'loyalty_program_id' => $program->id,
                    'sort_order'         => 1,
                    'reward_title'       => $program->reward_title ?? 'Premio',
                    'reward_description' => $program->reward_description,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

            DB::table('loyalty_milestones')
                ->where('loyalty_program_id', $program->id)
                ->whereNull('loyalty_prize_system_id')
                ->update(['loyalty_prize_system_id' => $psId]);
        }

        // 4. Drop old column from milestones; FK must go before its backing unique index (MySQL)
        if (Schema::hasColumn('loyalty_milestones', 'loyalty_program_id')) {
            Schema::table('loyalty_milestones', function (Blueprint $table) {
                $table->dropForeign(['loyalty_program_id']);
                $table->dropUnique(['loyalty_program_id', 'stamp_count']);
                $table->dropColumn('loyalty_program_id');
            });
        }

        Schema::table('loyalty_milestones', function (Blueprint $table) {
            $table->unsignedBigInteger('loyalty_prize_system_id')->nullable(false)->change();
        });

        // Add unique constraint only if it doesn't exist yet
        $indexes = DB::select("SHOW INDEX FROM loyalty_milestones WHERE Key_name = 'loyalty_milestones_loyalty_prize_system_id_stamp_count_unique'");
        if (empty($indexes)) {
            Schema::table('loyalty_milestones', function (Blueprint $table) {
                $table->unique(['loyalty_prize_system_id', 'stamp_count']);
            });
        }

        // 5. Track which prize system cycle a card is currently in (null = first)
        if (! Schema::hasColumn('loyalty_cards', 'current_prize_system_id')) {
            Schema::table('loyalty_cards', function (Blueprint $table) {
                $table->foreignId('current_prize_system_id')
                    ->nullable()
                    ->after('loyalty_program_id')
                    ->constrained('loyalty_prize_systems')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('loyalty_cards', function (Blueprint $table) {
            $table->dropForeign(['current_prize_system_id']);
            $table->dropColumn('current_prize_system_id');
        });

        Schema::table('loyalty_milestones', function (Blueprint $table) {
            $table->dropUnique(['loyalty_prize_system_id', 'stamp_count']);
            $table->unsignedBigInteger('loyalty_prize_system_id')->nullable()->change();
        });

        Schema::table('loyalty_milestones', function (Blueprint $table) {
            $table->foreignId('loyalty_program_id')
                ->nullable()
                ->after('loyalty_prize_system_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Restore program links from first prize system
        DB::table('loyalty_prize_systems')
            ->where('sort_order', 1)
            ->get()
            ->each(function ($ps) {
                DB::table('loyalty_milestones')
                    ->where('loyalty_prize_system_id', $ps->id)
                    ->update(['loyalty_program_id' => $ps->loyalty_program_id]);
            });

        Schema::table('loyalty_milestones', function (Blueprint $table) {
            $table->dropForeign(['loyalty_prize_system_id']);
            $table->dropColumn('loyalty_prize_system_id');
            $table->unique(['loyalty_program_id', 'stamp_count']);
        });

        Schema::dropIfExists('loyalty_prize_systems');
    }
};
