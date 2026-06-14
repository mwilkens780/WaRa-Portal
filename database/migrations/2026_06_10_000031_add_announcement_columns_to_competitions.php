<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            if (!Schema::hasColumn('competitions', 'ausrichter')) {
                $table->string('ausrichter')->nullable()->after('organizer');
            }
            if (!Schema::hasColumn('competitions', 'venue_details')) {
                $table->json('venue_details')->nullable()->after('ausrichter');
            }
            if (!Schema::hasColumn('competitions', 'kampfgericht')) {
                $table->json('kampfgericht')->nullable()->after('venue_details');
            }
            if (!Schema::hasColumn('competitions', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('kampfgericht');
            }
            if (!Schema::hasColumn('competitions', 'announcement_pdf_path')) {
                $table->string('announcement_pdf_path')->nullable()->after('contact_info');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('competitions', 'announcement_pdf_path') ? 'announcement_pdf_path' : null,
                Schema::hasColumn('competitions', 'contact_info')          ? 'contact_info'          : null,
                Schema::hasColumn('competitions', 'kampfgericht')          ? 'kampfgericht'          : null,
                Schema::hasColumn('competitions', 'venue_details')         ? 'venue_details'         : null,
                Schema::hasColumn('competitions', 'ausrichter')            ? 'ausrichter'            : null,
            ]);
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
};
