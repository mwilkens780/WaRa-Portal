<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kampfrichter_license_nr', 50)->nullable()->after('police_clearance_date');
            $table->date('kampfrichter_license_issued')->nullable()->after('kampfrichter_license_nr');
            $table->date('kampfrichter_license_valid_until')->nullable()->after('kampfrichter_license_issued');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kampfrichter_license_nr', 'kampfrichter_license_issued', 'kampfrichter_license_valid_until']);
        });
    }
};
