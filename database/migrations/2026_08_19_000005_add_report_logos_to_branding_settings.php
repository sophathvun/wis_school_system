<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->string('report_logo_1_path')->nullable()->after('footer_logo_path');
            $table->string('report_logo_2_path')->nullable()->after('report_logo_1_path');
        });

        DB::table('tb_branding_setting')->whereNull('report_logo_1_path')->update([
            'report_logo_1_path' => 'school_logo/student_profile_report_logo.png',
        ]);
    }

    public function down(): void
    {
        Schema::table('tb_branding_setting', function (Blueprint $table) {
            $table->dropColumn(['report_logo_1_path', 'report_logo_2_path']);
        });
    }
};
