<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_g12_certificate', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique();
        });
        DB::table('tb_g12_certificate')->select('id')->orderBy('id')->chunkById(200, function ($certificates) {
            foreach ($certificates as $certificate) {
                do {
                    $token = Str::random(40);
                } while (DB::table('tb_g12_certificate')->where('qr_token', $token)->exists());
                DB::table('tb_g12_certificate')->where('id', $certificate->id)->whereNull('qr_token')->update(['qr_token' => $token]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_g12_certificate', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
