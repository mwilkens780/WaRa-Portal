<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('swimmer_goals')) {
            Schema::create('swimmer_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
                $table->enum('type', ['time', 'qualification', 'free']);
                $table->string('title');
                $table->string('discipline', 50)->nullable();
                $table->integer('distance')->nullable();
                $table->enum('course', ['SCM', 'LCM'])->nullable();
                $table->integer('target_time_ms')->nullable();
                $table->enum('status', ['open', 'achieved', 'not_achieved', 'cancelled'])->default('open');
                $table->boolean('achieved')->default(false);
                $table->date('achieved_at')->nullable();
                $table->integer('achieved_time_ms')->nullable();
                $table->integer('progress')->default(0);
                $table->text('notes')->nullable();
                $table->boolean('notified')->default(true);
                $table->timestamps();
            });
        } else {
            // Table already exists (created manually) — add missing columns
            Schema::table('swimmer_goals', function (Blueprint $table) {
                if (!Schema::hasColumn('swimmer_goals', 'status')) {
                    $table->enum('status', ['open', 'achieved', 'not_achieved', 'cancelled'])
                          ->default('open')->after('target_time_ms');
                }
                if (!Schema::hasColumn('swimmer_goals', 'notified')) {
                    $table->boolean('notified')->default(true)->after('notes');
                }
                if (!Schema::hasColumn('swimmer_goals', 'achieved_time_ms')) {
                    $table->integer('achieved_time_ms')->nullable()->after('achieved_at');
                }
                if (!Schema::hasColumn('swimmer_goals', 'course')) {
                    $table->enum('course', ['SCM', 'LCM'])->nullable()->after('distance');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('swimmer_goals');
    }
};
