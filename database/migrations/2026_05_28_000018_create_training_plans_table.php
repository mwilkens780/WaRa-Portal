<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_plans')) {
            Schema::create('training_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_session_id')->unique()->constrained('training_sessions')->cascadeOnDelete();
                $table->text('description')->nullable();
                $table->string('attachment_path')->nullable();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('training_plan_blocks')) {
            Schema::create('training_plan_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_plan_id')->constrained('training_plans')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('label', 100)->nullable();      // optional block title, e.g. "Aufwärmen"
                $table->unsignedSmallInteger('repetitions')->nullable();
                $table->unsignedSmallInteger('distance')->nullable();
                $table->json('disciplines')->nullable();        // ["freistil","ruecken"]
                $table->json('additions')->nullable();          // ["Beine","Steigerung"]
                $table->text('comment')->nullable();
                $table->unsignedSmallInteger('start_interval_seconds')->nullable();
                $table->unsignedSmallInteger('recovery_seconds')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_blocks');
        Schema::dropIfExists('training_plans');
    }
};
