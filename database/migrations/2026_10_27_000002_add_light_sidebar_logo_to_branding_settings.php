<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->string('light_sidebar_logo_path')->nullable()->after('sidebar_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->dropColumn('light_sidebar_logo_path');
        });
    }
};
