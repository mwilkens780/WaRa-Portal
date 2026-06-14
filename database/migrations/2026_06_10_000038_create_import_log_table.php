<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_log', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['shsv', 'nsv', 'dsv', 'webclub_batch', 'manual']);
            $table->string('source_url', 500)->nullable();
            $table->string('filename', 255)->nullable();
            $table->enum('status', ['success', 'skipped', 'error']);
            $table->unsignedBigInteger('competition_id')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('imported_at')->useCurrent();

            $table->foreign('competition_id')->references('id')->on('competitions')->nullOnDelete();
            $table->index(['source', 'status']);
            $table->index('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_log');
    }
};
