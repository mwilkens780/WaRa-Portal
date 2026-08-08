<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('training_groups', 'webclub_id')) {
                // Numerische Gruppen-ID aus WebClub (aus grp[].grpID im Personen-Detail).
                // Wird für zuverlässiges Matching genutzt, Name-Matching als Fallback.
                $table->unsignedInteger('webclub_id')->nullable()->unique()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->dropUnique(['webclub_id']);
            $table->dropColumn('webclub_id');
        });
    }
};
