<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('competitions', 'organisation_notes')) {
            Schema::table('competitions', function (Blueprint $table) {
                $table->json('organisation_notes')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('organisation_notes');
        });
    }
};
