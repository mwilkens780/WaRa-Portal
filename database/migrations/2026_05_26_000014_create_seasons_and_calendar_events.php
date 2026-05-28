<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seasons')) {
            Schema::create('seasons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 20);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_current')->default(false);
                $table->timestamps();
            });

            DB::table('seasons')->insert([
                ['name' => '2022/23', 'start_date' => '2022-08-14', 'end_date' => '2023-07-16', 'is_current' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '2023/24', 'start_date' => '2023-08-27', 'end_date' => '2024-06-26', 'is_current' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '2024/25', 'start_date' => '2024-08-08', 'end_date' => '2025-07-09', 'is_current' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '2025/26', 'start_date' => '2025-08-21', 'end_date' => '2026-07-01', 'is_current' => true,  'created_at' => now(), 'updated_at' => now()],
                ['name' => '2026/27', 'start_date' => '2026-08-13', 'end_date' => '2027-06-30', 'is_current' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('type', 30)->default('sonstiges');
                $table->unsignedBigInteger('season_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('seasons');
    }
};
