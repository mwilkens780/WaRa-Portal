<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_session_group')) {
            Schema::create('training_session_group', function (Blueprint $table) {
                $table->unsignedBigInteger('training_session_id');
                $table->unsignedBigInteger('training_group_id');
                $table->primary(['training_session_id', 'training_group_id']);
            });
        }

        if (Schema::hasColumn('training_sessions', 'training_group_id')) {
            DB::statement('
                INSERT IGNORE INTO training_session_group (training_session_id, training_group_id)
                SELECT id, training_group_id
                FROM training_sessions
                WHERE training_group_id IS NOT NULL
            ');

            Schema::table('training_sessions', function (Blueprint $table) {
                $table->dropColumn('training_group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('training_group_id')->nullable()->after('trainer_id');
        });

        Schema::dropIfExists('training_session_group');
    }
};
