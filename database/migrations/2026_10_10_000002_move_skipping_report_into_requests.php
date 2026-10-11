<?php

use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->removeReportTab();
        DB::table('access_permissions')->where('code','student-skipping-grade.print-reports')
            ->update(['name'=>StudentSkippingGradePermissions::catalog()['print-reports'],'updated_at'=>now()]);
    }

    private function removeReportTab(): void
    {
        $id=DB::table('access_permissions')->where('code','student-skipping-grade.reports')->value('id');
        if (!$id) return;
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) {
            DB::table($table)->where('permission_id',$id)->delete();
        }
        DB::table('access_permissions')->where('id',$id)->delete();
    }

    public function down(): void
    {
        // Restore the former tab permission and its inherited assignments when rolling back.
        (require __DIR__.'/2026_10_10_000001_add_skipping_report_permissions.php')->up();
    }
};
