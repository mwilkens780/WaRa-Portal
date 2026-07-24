<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('health_documents');
        Schema::create('health_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('category', ['nutrition', 'sports_medicine']);
            $table->string('title');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category'], 'hd_user_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_documents');
    }
};
