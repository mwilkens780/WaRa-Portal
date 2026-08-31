<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->boolean('motto_week_enabled')->default(false)->after('webclub_id');
        });

        Schema::create('group_motto_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('week_start');
            $table->text('motto')->nullable();
            $table->text('generated_motto')->nullable();
            $table->timestamps();
            $table->unique(['training_group_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_motto_weeks');
        Schema::table('training_groups', function (Blueprint $table) {
            $table->dropColumn('motto_week_enabled');
        });
    }
};
