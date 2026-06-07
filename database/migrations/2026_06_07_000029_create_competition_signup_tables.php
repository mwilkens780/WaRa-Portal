<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competition_signup_requests')) {
            Schema::create('competition_signup_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                $table->text('message')->nullable();
                $table->string('attachment_path')->nullable();
                $table->date('deadline')->nullable();
                $table->json('eligible_group_ids')->nullable();
                $table->json('eligible_user_ids')->nullable();
                $table->foreignId('created_by_id')->constrained('users');
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('competition_signup_responses')) {
            Schema::create('competition_signup_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_signup_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('status', ['pending', 'attending', 'not_attending'])->default('pending');
                $table->text('note')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->unique(['competition_signup_request_id', 'user_id']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_signup_responses');
        Schema::dropIfExists('competition_signup_requests');
    }
};
