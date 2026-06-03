<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'street')) {
                $table->string('street', 255)->nullable()->after('training_group');
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('street');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 50)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'email2')) {
                $table->string('email2', 255)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'trainer_license_nr')) {
                $table->string('trainer_license_nr', 50)->nullable()->after('additional_roles');
            }
            if (!Schema::hasColumn('users', 'trainer_license_valid_until')) {
                $table->date('trainer_license_valid_until')->nullable()->after('trainer_license_nr');
            }
            if (!Schema::hasColumn('users', 'rescue_certificate_until')) {
                $table->date('rescue_certificate_until')->nullable()->after('trainer_license_valid_until');
            }
            if (!Schema::hasColumn('users', 'first_aid_until')) {
                $table->date('first_aid_until')->nullable()->after('rescue_certificate_until');
            }
            if (!Schema::hasColumn('users', 'police_clearance_date')) {
                $table->date('police_clearance_date')->nullable()->after('first_aid_until');
            }
            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('police_clearance_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'street', 'postal_code', 'city', 'country',
                'mobile', 'email2',
                'trainer_license_nr', 'trainer_license_valid_until',
                'rescue_certificate_until', 'first_aid_until',
                'police_clearance_date', 'notes',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('users', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
