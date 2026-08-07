<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_academic_year')
            ->where('period_type', 'summer')
            ->whereNull('parent_academic_year_id')
            ->get(['id', 'academic_year'])
            ->each(function ($summer) {
                if (!preg_match('/summer\s+(\d{4})/i', (string) $summer->academic_year, $matches)) {
                    return;
                }

                $endYear = (int) $matches[1];
                $parent = DB::table('tb_academic_year')
                    ->where('period_type', 'regular')
                    ->where('academic_year', 'like', ($endYear - 1) . '-' . $endYear)
                    ->value('id');

                if ($parent) {
                    DB::table('tb_academic_year')->where('id', $summer->id)->update(['parent_academic_year_id' => $parent]);
                }
            });
    }

    public function down(): void
    {
        // Existing parent links are safe metadata and are intentionally retained on rollback.
    }
};
