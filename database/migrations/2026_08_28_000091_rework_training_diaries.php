<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // perceived_intensity → self_score migrieren (NULL → NULL, Wert bleibt)
        Schema::table('training_diaries', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_score')->nullable()->after('user_id');
            $table->unsignedTinyInteger('trainer_score')->nullable()->after('self_score');
        });

        // Vorhandene perceived_intensity-Werte übernehmen
        DB::statement('UPDATE training_diaries SET self_score = perceived_intensity WHERE perceived_intensity IS NOT NULL');

        Schema::table('training_diaries', function (Blueprint $table) {
            $table->dropColumn(['body', 'mood', 'perceived_intensity']);
        });
    }

    public function down(): void
    {
        Schema::table('training_diaries', function (Blueprint $table) {
            $table->text('body')->nullable();
            $table->enum('mood', ['sehr_gut','gut','mittel','schlecht','sehr_schlecht'])->nullable();
            $table->unsignedTinyInteger('perceived_intensity')->nullable();
        });

        DB::statement('UPDATE training_diaries SET perceived_intensity = self_score WHERE self_score IS NOT NULL');

        Schema::table('training_diaries', function (Blueprint $table) {
            $table->dropColumn(['self_score', 'trainer_score']);
        });
    }
};
