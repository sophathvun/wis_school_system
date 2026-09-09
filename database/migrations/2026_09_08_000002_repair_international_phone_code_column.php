<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tb_country', 'international_phone_code')) {
            Schema::table('tb_country', function (Blueprint $table) {
                $table->string('international_phone_code', 20)->nullable()->after('country_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tb_country', 'international_phone_code')) {
            Schema::table('tb_country', function (Blueprint $table) {
                $table->dropColumn('international_phone_code');
            });
        }
    }
};
