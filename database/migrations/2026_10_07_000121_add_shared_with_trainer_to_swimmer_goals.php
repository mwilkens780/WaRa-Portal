<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Ziele: Der Sportler entscheidet je Ziel, ob Trainer es sehen und
 * kommentieren dürfen (Martin, 07.10.2026). Bestehende Ziele bleiben freigegeben –
 * so haben die Trainer sie bisher gesehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swimmer_goals', function (Blueprint $table) {
            $table->boolean('shared_with_trainer')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('swimmer_goals', function (Blueprint $table) {
            $table->dropColumn('shared_with_trainer');
        });
    }
};
