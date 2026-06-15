<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('menu_key', 50);
            $table->boolean('allowed')->default(false);
            $table->timestamps();

            $table->unique(['role', 'menu_key']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_permissions');
    }
};
