<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swimmer_series_exclusions', function (Blueprint $table) {
            if (!Schema::hasColumn('swimmer_series_exclusions', 'comment')) {
                $table->text('comment')->nullable()->after('recurrence_group_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('swimmer_series_exclusions', function (Blueprint $table) {
            if (Schema::hasColumn('swimmer_series_exclusions', 'comment')) {
                $table->dropColumn('comment');
            }
        });
    }
};
