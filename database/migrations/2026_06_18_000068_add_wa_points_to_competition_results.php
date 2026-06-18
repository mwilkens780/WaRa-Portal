<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('wa_points')->nullable()->after('is_final');
            $table->unsignedSmallInteger('wa_table_year')->nullable()->after('wa_points');
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn(['wa_points', 'wa_table_year']);
        });
    }
};
