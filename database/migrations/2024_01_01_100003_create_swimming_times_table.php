<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swimming_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('training_session_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('discipline', ['F', 'B', 'R', 'S', 'L']);
            $table->unsignedSmallInteger('distance'); // in Metern: 25, 50, 100, 200, 400, 800, 1500
            $table->unsignedInteger('time_ms'); // Zeit in Millisekunden
            $table->boolean('is_personal_best')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swimming_times');
    }
};
