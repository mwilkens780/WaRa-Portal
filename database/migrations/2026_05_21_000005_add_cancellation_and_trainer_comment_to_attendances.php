<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('training_attendances', 'pre_absent')) {
                $table->boolean('pre_absent')->default(false)->after('attended');
            }
            if (!Schema::hasColumn('training_attendances', 'pre_absent_note')) {
                $table->string('pre_absent_note', 500)->nullable()->after('pre_absent');
            }
            if (!Schema::hasColumn('training_attendances', 'trainer_comment')) {
                $table->text('trainer_comment')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_attendances', function (Blueprint $table) {
            $table->dropColumn(['pre_absent', 'pre_absent_note', 'trainer_comment']);
        });
    }
};
