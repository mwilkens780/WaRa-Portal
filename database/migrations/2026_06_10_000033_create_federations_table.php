<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federations', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->autoIncrement();
            $table->string('slug', 10)->unique();
            $table->string('name', 100);
            $table->string('url', 255)->nullable();
        });

        DB::table('federations')->insert([
            ['id' => 1, 'slug' => 'shsv', 'name' => 'Schleswig-Holsteinischer Schwimmverband', 'url' => 'https://shsv.de'],
            ['id' => 2, 'slug' => 'nsv',  'name' => 'Norddeutscher Schwimmverband',            'url' => 'https://nsv-schwimmen.de'],
            ['id' => 3, 'slug' => 'dsv',  'name' => 'Deutscher Schwimm-Verband',               'url' => 'https://dsv.de'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('federations');
    }
};
