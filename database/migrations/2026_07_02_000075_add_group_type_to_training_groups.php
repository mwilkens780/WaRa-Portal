<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->enum('group_type', ['leistungssport', 'breitensport', 'triathlon', 'synchronschwimmen'])
                  ->default('breitensport')
                  ->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }
};
