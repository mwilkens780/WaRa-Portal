<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('discipline', ['F', 'B', 'R', 'S', 'L']);
            $table->unsignedSmallInteger('distance'); // in Metern
            $table->unsignedInteger('time_ms'); // Zeit in Millisekunden
            $table->unsignedSmallInteger('placement')->nullable(); // Platzierung
            $table->boolean('is_personal_best')->default(false);
            $table->string('age_group')->nullable(); // Altersklasse z.B. AK12, AK14
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_results');
    }
};
