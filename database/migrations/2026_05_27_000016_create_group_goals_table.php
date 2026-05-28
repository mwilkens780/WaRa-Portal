<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_goals')) return;

        Schema::create('group_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('target_count')->nullable();
            $table->integer('achieved_count')->nullable();
            $table->boolean('achieved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_goals');
    }
};
