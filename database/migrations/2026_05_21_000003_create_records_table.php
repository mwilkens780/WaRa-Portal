<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('records')) return;

        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['vereinsrekord', 'landesrekord']);
            $table->enum('discipline', ['freistil', 'brust', 'ruecken', 'schmetterling', 'lagen']);
            $table->unsignedSmallInteger('distance');
            $table->enum('gender', ['M', 'F']);
            $table->string('age_group', 20)->nullable(); // null = Offene Klasse
            $table->enum('course', ['LCM', 'SCM'])->default('LCM');
            $table->string('swimmer_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('time_ms');
            $table->date('set_date')->nullable();
            $table->string('location', 255)->nullable();
            // Points to the competition result that set this record (null if imported manually)
            $table->foreignId('competition_result_id')->nullable()
                ->constrained('competition_results')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
