<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('competition_event_id')->nullable();
            $table->string('discipline', 20);
            $table->unsignedSmallInteger('distance');
            $table->char('gender', 1);
            $table->string('age_group', 50)->nullable();
            $table->unsignedInteger('entry_time_ms')->nullable();
            $table->enum('status', ['entered', 'scratched'])->default('entered');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('competition_event_id')->references('id')->on('competition_events')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['competition_id', 'user_id', 'discipline', 'distance', 'age_group'], 'uq_entry');
        });

        Schema::create('competition_relay_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('competition_event_id')->nullable();
            $table->string('discipline', 20);
            $table->unsignedSmallInteger('distance');
            $table->enum('gender', ['M', 'F', 'mixed']);
            $table->string('age_group', 50)->nullable();
            $table->unsignedInteger('entry_time_ms')->nullable();
            $table->enum('status', ['entered', 'scratched'])->default('entered');
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('competition_event_id')->references('id')->on('competition_events')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('competition_relay_entry_members', function (Blueprint $table) {
            $table->unsignedBigInteger('relay_entry_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('position');

            $table->primary(['relay_entry_id', 'position']);
            $table->foreign('relay_entry_id')->references('id')->on('competition_relay_entries')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_relay_entry_members');
        Schema::dropIfExists('competition_relay_entries');
        Schema::dropIfExists('competition_entries');
    }
};
