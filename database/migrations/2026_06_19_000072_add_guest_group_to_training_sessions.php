<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('guest_group_id')
                  ->nullable()
                  ->after('max_participants')
                  ->constrained('training_groups')
                  ->nullOnDelete();
        });

        Schema::table('training_session_swimmers', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('recurrence_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['guest_group_id']);
            $table->dropColumn('guest_group_id');
        });

        Schema::table('training_session_swimmers', function (Blueprint $table) {
            $table->dropColumn('is_guest');
        });
    }
};
