<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['lane', 'pool', 'room']);
            $table->string('color', 7)->default('#3B82F6');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hall_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Mon … 7=Sun
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label');
            $table->enum('type', ['training', 'course', 'school', 'external', 'maintenance', 'other'])->default('training');
            $table->foreignId('training_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained('training_sessions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('color', 7)->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
        });

        // Seed default resources
        $now = now();
        DB::table('hall_resources')->insert([
            ['name' => 'Bahn 1',                'type' => 'lane', 'color' => '#3B82F6', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bahn 2',                'type' => 'lane', 'color' => '#10B981', 'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bahn 3',                'type' => 'lane', 'color' => '#F59E0B', 'sort_order' => 3, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bahn 4',                'type' => 'lane', 'color' => '#EF4444', 'sort_order' => 4, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nichtschwimmerbecken',  'type' => 'pool', 'color' => '#8B5CF6', 'sort_order' => 5, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mehrzweckraum',          'type' => 'room', 'color' => '#6B7280', 'sort_order' => 6, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_bookings');
        Schema::dropIfExists('hall_resources');
    }
};
