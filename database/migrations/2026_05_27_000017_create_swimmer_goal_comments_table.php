<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('swimmer_goal_comments')) return;

        Schema::create('swimmer_goal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swimmer_goal_id')->constrained('swimmer_goals')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swimmer_goal_comments');
    }
};
