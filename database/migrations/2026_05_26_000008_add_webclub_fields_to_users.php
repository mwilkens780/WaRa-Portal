<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gender')) {
                $table->enum('gender', ['M', 'F'])->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'dsv_id')) {
                $table->string('dsv_id', 20)->nullable()->unique()->after('gender');
            }
            if (!Schema::hasColumn('users', 'membership_number')) {
                $table->string('membership_number', 30)->nullable()->after('dsv_id');
            }
            if (!Schema::hasColumn('users', 'member_since')) {
                $table->date('member_since')->nullable()->after('membership_number');
            }
            if (!Schema::hasColumn('users', 'training_group')) {
                $table->string('training_group', 50)->nullable()->after('member_since');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dsv_id', 'membership_number', 'member_since', 'training_group']);
        });
    }
};
