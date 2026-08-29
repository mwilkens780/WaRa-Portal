<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('training_plan_blocks', 'time_tracking')) {
            Schema::table('training_plan_blocks', function (Blueprint $table) {
                $table->boolean('time_tracking')->default(false)->after('repetitions_nested');
            });

            // Preserve current behaviour: until now every block with repetitions
            // rendered a time grid, so existing blocks keep their grid.
            DB::table('training_plan_blocks')
                ->where('repetitions', '>', 0)
                ->update(['time_tracking' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('training_plan_blocks', function (Blueprint $table) {
            $table->dropColumn('time_tracking');
        });
    }
};
