<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            if (!Schema::hasColumn('competitions', 'season_id')) {
                $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete()->after('date_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            if (Schema::hasColumn('competitions', 'season_id')) {
                $table->dropForeign(['season_id']);
                $table->dropColumn('season_id');
            }
        });
    }
};
