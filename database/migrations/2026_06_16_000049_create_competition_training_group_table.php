<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_training_group', function (Blueprint $table) {
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['competition_id', 'training_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_training_group');
    }
};
