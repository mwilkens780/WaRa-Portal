<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jahr des Rekords, wenn das genaue Datum nicht bekannt ist.
 *
 * Die alte Vereinsrekordliste (Word) nennt nur "Name (Jahrgang), Jahr". Ein
 * erfundenes Datum wie 01.01.1991 waere falsch, also steht das Jahr allein.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->unsignedSmallInteger('set_year')->nullable()->after('set_date');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('set_year');
        });
    }
};
