<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_list_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('list_type', ['eternal', 'annual']);
            $table->enum('discipline', ['F', 'B', 'R', 'S', 'L']);
            $table->unsignedSmallInteger('distance');
            $table->enum('gender', ['M', 'F']);
            $table->unsignedSmallInteger('birth_year')->nullable(); // null = open class (not used yet)
            $table->enum('course', ['Langbahn', 'Kurzbahn'])->default('Langbahn');
            $table->unsignedSmallInteger('set_year')->nullable(); // only for annual
            $table->string('swimmer_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('time_ms');
            $table->date('set_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('competition_result_id')->nullable()
                ->constrained('competition_results')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['list_type', 'discipline', 'distance', 'gender', 'birth_year', 'course', 'set_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_list_entries');
    }
};
