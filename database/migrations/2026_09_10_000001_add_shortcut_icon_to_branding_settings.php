<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->string('shortcut_icon_path')->nullable()->after('login_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->dropColumn('shortcut_icon_path');
        });
    }
};
