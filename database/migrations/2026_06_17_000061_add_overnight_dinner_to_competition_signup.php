<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_signup_requests', 'offer_overnight')) {
                $table->boolean('offer_overnight')->default(false)->after('bus_seats');
            }
            if (!Schema::hasColumn('competition_signup_requests', 'offer_dinner')) {
                $table->boolean('offer_dinner')->default(false)->after('offer_overnight');
            }
        });

        Schema::table('competition_signup_responses', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_signup_responses', 'wants_overnight')) {
                $table->boolean('wants_overnight')->default(false)->after('bus_booked');
            }
            if (!Schema::hasColumn('competition_signup_responses', 'wants_dinner')) {
                $table->boolean('wants_dinner')->default(false)->after('wants_overnight');
            }
            if (!Schema::hasColumn('competition_signup_responses', 'carpool_seats')) {
                $table->unsignedTinyInteger('carpool_seats')->nullable()->after('wants_dinner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->dropColumn(array_filter(['offer_overnight', 'offer_dinner'], fn($c) => Schema::hasColumn('competition_signup_requests', $c)));
        });

        Schema::table('competition_signup_responses', function (Blueprint $table) {
            $table->dropColumn(array_filter(['wants_overnight', 'wants_dinner', 'carpool_seats'], fn($c) => Schema::hasColumn('competition_signup_responses', $c)));
        });
    }
};
