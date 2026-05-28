<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transaction_logs')) {
            Schema::create('transaction_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 150);
                $table->enum('action', ['created', 'updated', 'deleted']);
                $table->string('model_type', 80);
                $table->unsignedBigInteger('model_id')->nullable();
                $table->string('model_label', 255)->nullable();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('app_traces')) {
            Schema::create('app_traces', function (Blueprint $table) {
                $table->id();
                $table->tinyInteger('level');
                $table->string('message', 500);
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key', 80)->primary();
                $table->text('value')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        // Insert defaults only if not already present
        $existing = DB::table('settings')->whereIn('key', ['transaction_log_enabled', 'trace_level'])->pluck('key');
        $inserts = collect([
            ['key' => 'transaction_log_enabled', 'value' => '1'],
            ['key' => 'trace_level',             'value' => '1'],
        ])->reject(fn($row) => $existing->contains($row['key']))->values()->all();
        if ($inserts) {
            DB::table('settings')->insert($inserts);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
        Schema::dropIfExists('app_traces');
        Schema::dropIfExists('settings');
    }
};
