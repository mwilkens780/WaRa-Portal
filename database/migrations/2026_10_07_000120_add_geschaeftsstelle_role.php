<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Neue Portal-Rolle "Geschäftsstelle" (Auftrag Martin, 07.10.2026): Benutzer-
 * verwaltung, Zuweisung zu Gruppen/Kursen und Terminen, Kampfrichter-Lizenzen.
 */
return new class extends Migration
{
    private const BEFORE = "'admin','trainer','schwimmer','elternteil','kampfrichter','vorstand','ernaehrungsberater','teamarzt'";
    private const AFTER  = "'admin','trainer','schwimmer','elternteil','kampfrichter','vorstand','geschaeftsstelle','ernaehrungsberater','teamarzt'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        $col = collect(DB::select("SHOW COLUMNS FROM users WHERE Field = 'role'"))->first();
        $null = ($col?->Null ?? 'NO') === 'YES' ? 'NULL' : 'NOT NULL';
        $def  = $col?->Default !== null ? " DEFAULT '{$col->Default}'" : '';
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM(" . self::AFTER . ") {$null}{$def}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::table('users')->where('role', 'geschaeftsstelle')->update(['role' => 'vorstand']);
        $col = collect(DB::select("SHOW COLUMNS FROM users WHERE Field = 'role'"))->first();
        $null = ($col?->Null ?? 'NO') === 'YES' ? 'NULL' : 'NOT NULL';
        $def  = $col?->Default !== null ? " DEFAULT '{$col->Default}'" : '';
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM(" . self::BEFORE . ") {$null}{$def}");
    }
};
