<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->json('wertungen')->nullable()->after('age_group');
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('wertungen');
        });
    }
};
