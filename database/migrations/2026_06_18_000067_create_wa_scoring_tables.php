<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_scoring_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('pool_length'); // 25 oder 50
            $table->enum('gender', ['M', 'F']);
            $table->enum('discipline', ['F', 'B', 'R', 'S', 'L']);
            $table->unsignedSmallInteger('distance_m');
            $table->unsignedInteger('base_time_ms');
            $table->timestamps();

            $table->unique(['year', 'pool_length', 'gender', 'discipline', 'distance_m'], 'wa_scoring_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_scoring_tables');
    }
};
