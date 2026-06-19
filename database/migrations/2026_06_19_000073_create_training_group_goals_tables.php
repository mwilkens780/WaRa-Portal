<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_group_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['quantitative', 'qualitative'])->default('qualitative');
            $table->string('target_value', 255)->nullable(); // e.g. "80%", "1x NDM Norm"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_group_goal_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_goal_id')->constrained('training_group_goals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();     // swimmer
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete(); // who evaluated
            $table->enum('evaluation_type', ['self', 'trainer']);
            $table->tinyInteger('rating')->nullable(); // 1–5
            $table->string('current_value', 100)->nullable(); // quantitative progress, e.g. "65%"
            $table->text('notes')->nullable();
            $table->date('evaluated_at');
            $table->timestamps();

            // One evaluation per swimmer per goal per type (latest wins via updateOrCreate)
            $table->unique(['training_group_goal_id', 'user_id', 'evaluation_type'], 'goal_eval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_group_goal_evaluations');
        Schema::dropIfExists('training_group_goals');
    }
};
