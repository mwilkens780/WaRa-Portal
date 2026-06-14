<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            if (!Schema::hasColumn('competitions', 'analysis_text')) {
                $table->text('analysis_text')->nullable()->after('description')
                      ->comment('Vom Trainer validierter HTML-Auswertungstext (Quill)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('analysis_text');
        });
    }
};
