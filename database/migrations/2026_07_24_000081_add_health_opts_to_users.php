<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('opt_nutrition')->default(false)->after('notes');
            $table->boolean('opt_sports_medicine')->default(false)->after('opt_nutrition');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['opt_nutrition', 'opt_sports_medicine']);
        });
    }
};
