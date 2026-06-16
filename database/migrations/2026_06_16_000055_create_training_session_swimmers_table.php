<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_session_swimmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Either session-level or series-level assignment (exactly one must be set)
            $table->foreignId('training_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('recurrence_group_id', 36)->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'training_session_id'], 'unique_swimmer_session');
            $table->unique(['user_id', 'recurrence_group_id'], 'unique_swimmer_series');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_swimmers');
    }
};
