<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('training_plan_blocks', 'repetitions_nested')) {
            Schema::table('training_plan_blocks', function (Blueprint $table) {
                // Stores the multi-level structure, e.g. [4, 6, 2] for "4×6×2×100m"
                // The existing integer `repetitions` column keeps the product for backward compat.
                $table->json('repetitions_nested')->nullable()->after('repetitions');
            });

            // Migrate existing data: wrap integer into single-element array
            DB::table('training_plan_blocks')
                ->whereNotNull('repetitions')
                ->where('repetitions', '>', 0)
                ->each(fn($b) =>
                    DB::table('training_plan_blocks')
                        ->where('id', $b->id)
                        ->update(['repetitions_nested' => json_encode([$b->repetitions])])
                );
        }
    }

    public function down(): void
    {
        Schema::table('training_plan_blocks', function (Blueprint $table) {
            $table->dropColumn('repetitions_nested');
        });
    }
};
