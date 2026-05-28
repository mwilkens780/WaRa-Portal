<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('training_plan_blocks', 'materials')) {
            Schema::table('training_plan_blocks', function (Blueprint $table) {
                $table->json('materials')->nullable()->after('additions');
            });
        }

        if (!Schema::hasTable('training_block_times')) {
            Schema::create('training_block_times', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_plan_block_id')->constrained('training_plan_blocks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('repetition');
                $table->unsignedInteger('time_cs')->nullable(); // centiseconds, null = not swum
                $table->timestamps();
                $table->unique(['training_plan_block_id', 'user_id', 'repetition'], 'block_user_rep_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_block_times');
        if (Schema::hasColumn('training_plan_blocks', 'materials')) {
            Schema::table('training_plan_blocks', function (Blueprint $table) {
                $table->dropColumn('materials');
            });
        }
    }
};
