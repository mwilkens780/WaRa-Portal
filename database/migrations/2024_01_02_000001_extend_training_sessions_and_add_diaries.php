<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Neue Trainingstypen zur ENUM hinzufügen
        DB::statement("ALTER TABLE training_sessions MODIFY type ENUM(
            'kondition','technik','wettkampf','ausdauer',
            'krafttraining','physio','mentaltraining','sonstiges'
        ) NOT NULL DEFAULT 'technik'");

        Schema::table('training_sessions', function (Blueprint $table) {
            // Wiederholungen
            $table->enum('recurrence_type', ['none','weekly','biweekly','monthly'])
                  ->default('none')->after('notes');
            $table->date('recurrence_until')->nullable()->after('recurrence_type');
            $table->string('recurrence_group_id', 36)->nullable()->index()->after('recurrence_until');

            // Anhänge
            $table->string('team_plan_path')->nullable()->after('recurrence_group_id');
            $table->string('individual_plan_path')->nullable()->after('team_plan_path');
        });

        // Trainingstagebuch
        Schema::create('training_diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body')->nullable();
            $table->enum('mood', ['sehr_gut','gut','mittel','schlecht','sehr_schlecht'])->nullable();
            $table->unsignedTinyInteger('perceived_intensity')->nullable(); // 1-10
            $table->unique(['training_session_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_diaries');

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'recurrence_type','recurrence_until','recurrence_group_id',
                'team_plan_path','individual_plan_path',
            ]);
        });

        DB::statement("ALTER TABLE training_sessions MODIFY type ENUM(
            'kondition','technik','wettkampf','ausdauer','sonstiges'
        ) NOT NULL DEFAULT 'technik'");
    }
};
