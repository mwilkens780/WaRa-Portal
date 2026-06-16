<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->string('meeting_point', 255)->nullable()->after('deadline');
            $table->time('meeting_time')->nullable()->after('meeting_point');
            $table->boolean('bus_available')->default(false)->after('meeting_time');
            $table->unsignedSmallInteger('bus_seats')->default(8)->after('bus_available');
        });

        Schema::table('competition_signup_responses', function (Blueprint $table) {
            $table->boolean('bus_booked')->default(false)->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->dropColumn(['meeting_point', 'meeting_time', 'bus_available', 'bus_seats']);
        });
        Schema::table('competition_signup_responses', function (Blueprint $table) {
            $table->dropColumn('bus_booked');
        });
    }
};
