<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_g12_exam_certificate', function (Blueprint $table) {
            $table->string('remark', 500)->nullable()->after('recipient');
        });

        $remark = 'Certificate not issued — Failed National Examination | មិនមានវិញ្ញាបនបត្រ — មិនបានជាប់ការប្រឡងថ្នាក់ជាតិ';
        $failedRecordIds = DB::table('tb_g12_exam_record')
            ->where('result_status', 'failed')
            ->orWhereRaw('UPPER(overall_grade) = ?', ['F'])
            ->pluck('id');

        DB::table('tb_g12_exam_certificate')
            ->whereIn('record_id', $failedRecordIds)
            ->update([
                'status' => 'not_applicable',
                'number' => null,
                'available_date' => null,
                'collected_date' => null,
                'recipient' => null,
                'remark' => $remark,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('tb_g12_exam_certificate', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
