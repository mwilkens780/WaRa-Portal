<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsgvo_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('type');        // auskunft|berichtigung|loeschung|portabilitaet|widerspruch
            $table->text('description')->nullable();
            $table->string('status')->default('offen');  // offen|in_bearbeitung|abgeschlossen
            $table->text('admin_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsgvo_requests');
    }
};
