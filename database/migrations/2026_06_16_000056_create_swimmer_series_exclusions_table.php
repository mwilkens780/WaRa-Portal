<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swimmer_series_exclusions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recurrence_group_id', 36);
            $table->primary(['user_id', 'recurrence_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swimmer_series_exclusions');
    }
};
