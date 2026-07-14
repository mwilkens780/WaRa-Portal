<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table may already exist (partially) from a failed earlier run — drop it first
        Schema::dropIfExists('best_list_entries');

        Schema::create('best_list_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('list_type', ['eternal', 'annual']);
            $table->enum('discipline', ['F', 'B', 'R', 'S', 'L']);
            $table->unsignedSmallInteger('distance');
            $table->enum('gender', ['M', 'F']);
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->enum('course', ['Langbahn', 'Kurzbahn'])->default('Langbahn');
            $table->unsignedSmallInteger('set_year')->nullable();
            $table->string('swimmer_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('time_ms');
            $table->date('set_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('competition_result_id')->nullable()
                ->constrained('competition_results')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Explicit short name — MySQL limits index names to 64 chars
            $table->index(
                ['list_type', 'discipline', 'distance', 'gender', 'birth_year', 'course', 'set_year'],
                'bl_entries_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_list_entries');
    }
};
