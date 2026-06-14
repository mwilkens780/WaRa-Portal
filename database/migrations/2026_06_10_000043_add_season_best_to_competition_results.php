<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->boolean('is_season_best')->default(false)->after('is_personal_best');
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('is_season_best');
        });
    }
};
