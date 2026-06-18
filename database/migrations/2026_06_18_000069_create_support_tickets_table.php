<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');                          // bug | enhancement
            $table->string('title');
            $table->text('description');
            $table->integer('github_issue_number')->nullable()->index();
            $table->string('github_issue_url')->nullable();
            $table->boolean('notify_on_close')->default(false);
            $table->timestamp('github_closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
