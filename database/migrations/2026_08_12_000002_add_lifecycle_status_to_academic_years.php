<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_academic_year', function (Blueprint $table) {
            $table->string('lifecycle_status', 20)->default('pending')->after('status');
            $table->index(['period_type', 'lifecycle_status'], 'academic_year_period_lifecycle_index');
        });

        $currentRegularId = DB::table('tb_student_enrollment')
            ->join('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
            ->where('tb_academic_year.period_type', 'regular')
            ->where('tb_student_enrollment.status', 1)
            ->where('tb_student_enrollment.enrollment_status', 'active')
            ->max('tb_academic_year.id');

        if (!$currentRegularId) {
            $currentRegularId = DB::table('tb_academic_year')
                ->where('period_type', 'regular')
                ->where('status', 1)
                ->max('id');
        }

        DB::table('tb_academic_year')
            ->where('period_type', 'regular')
            ->where('status', 0)
            ->update(['lifecycle_status' => 'finished']);

        DB::table('tb_academic_year')
            ->where('period_type', 'regular')
            ->where('status', 1)
            ->when($currentRegularId, fn ($query) => $query->where('id', '<', $currentRegularId))
            ->update(['lifecycle_status' => 'finished', 'status' => 0]);

        if ($currentRegularId) {
            DB::table('tb_academic_year')
                ->where('id', $currentRegularId)
                ->update(['lifecycle_status' => 'started', 'status' => 1]);

            DB::table('tb_academic_year')
                ->where('period_type', 'regular')
                ->where('status', 1)
                ->where('id', '>', $currentRegularId)
                ->update(['lifecycle_status' => 'pending', 'status' => 1]);
        }

        DB::table('tb_academic_year')
            ->where('period_type', 'summer')
            ->where('status', 1)
            ->update(['lifecycle_status' => 'pending']);

        DB::table('tb_academic_year')
            ->where('period_type', 'summer')
            ->where('status', 0)
            ->update(['lifecycle_status' => 'finished']);
    }

    public function down(): void
    {
        Schema::table('tb_academic_year', function (Blueprint $table) {
            $table->dropIndex('academic_year_period_lifecycle_index');
            $table->dropColumn('lifecycle_status');
        });
    }
};
