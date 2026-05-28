<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_groups')) {
            Schema::create('training_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('color', 20)->default('blue');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('training_group_trainer')) {
            Schema::create('training_group_trainer', function (Blueprint $table) {
                $table->unsignedBigInteger('training_group_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['training_group_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('training_group_swimmer')) {
            Schema::create('training_group_swimmer', function (Blueprint $table) {
                $table->unsignedBigInteger('training_group_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['training_group_id', 'user_id']);
            });
        }

        Schema::table('training_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('training_sessions', 'training_group_id')) {
                $table->unsignedBigInteger('training_group_id')->nullable()->after('trainer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('training_group_id');
        });
        Schema::dropIfExists('training_group_trainer');
        Schema::dropIfExists('training_group_swimmer');
        Schema::dropIfExists('training_groups');
    }
};
