<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_academic_year', function (Blueprint $table) {
            $table->string('period_type', 20)->default('regular')->after('academic_year_code');
            $table->foreignId('parent_academic_year_id')->nullable()->after('period_type')->constrained('tb_academic_year')->nullOnDelete();
            $table->date('start_date')->nullable()->after('parent_academic_year_id');
            $table->date('end_date')->nullable()->after('start_date');
            $table->index('period_type');
        });

        DB::table('tb_academic_year')
            ->whereRaw("LOWER(academic_year) LIKE 'summer %'")
            ->update(['period_type' => 'summer']);
    }

    public function down(): void
    {
        Schema::table('tb_academic_year', function (Blueprint $table) {
            $table->dropForeign(['parent_academic_year_id']);
            $table->dropIndex(['period_type']);
            $table->dropColumn(['period_type', 'parent_academic_year_id', 'start_date', 'end_date']);
        });
    }
};
