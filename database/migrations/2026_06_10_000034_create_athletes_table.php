<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('athletes');

        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->string('lastname', 100);
            $table->string('firstname', 100);
            $table->unsignedSmallInteger('birth_year');
            $table->enum('gender', ['M', 'F', 'X']);
            $table->char('nationality', 3)->nullable();
            $table->string('club_name', 200)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('swimrankings_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['lastname', 'firstname', 'birth_year', 'gender'], 'uq_athlete');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
