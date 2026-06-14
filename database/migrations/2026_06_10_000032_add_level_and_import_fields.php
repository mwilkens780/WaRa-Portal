<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            if (!Schema::hasColumn('competitions', 'level')) {
                $table->string('level', 20)->nullable()->after('season_id')
                      ->comment('dsv_dm|dsv_djm|nsv|shsv_lm|shsv_open|vereins');
            }
            if (!Schema::hasColumn('competitions', 'federation_id')) {
                $table->unsignedTinyInteger('federation_id')->nullable()->after('level')
                      ->comment('FK federations — NULL = eigene Veranstaltung');
            }
            if (!Schema::hasColumn('competitions', 'source_file')) {
                $table->string('source_file')->nullable()->after('federation_id');
            }
            if (!Schema::hasColumn('competitions', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('source_file');
            }
            if (!Schema::hasColumn('competitions', 'import_hash')) {
                $table->char('import_hash', 64)->nullable()->unique()->after('source_url')
                      ->comment('SHA-256 des DSV7-Dateiinhalts');
            }
        });

        Schema::table('competition_events', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_events', 'dsv_wertungs_id')) {
                $table->unsignedInteger('dsv_wertungs_id')->nullable()->after('event_number')
                      ->comment('WertungsID aus DSV7 WERTUNG-Record');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            foreach (['level', 'federation_id', 'source_file', 'source_url', 'import_hash'] as $col) {
                if (Schema::hasColumn('competitions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('competition_events', function (Blueprint $table) {
            if (Schema::hasColumn('competition_events', 'dsv_wertungs_id')) {
                $table->dropColumn('dsv_wertungs_id');
            }
        });
    }
};
