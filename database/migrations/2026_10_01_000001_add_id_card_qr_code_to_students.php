<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            if (! Schema::hasColumn('tb_student', 'id_card_qr_code')) {
                $table->string('id_card_qr_code', 40)->nullable()->after('student_id')->unique();
            }

            if (! Schema::hasColumn('tb_student', 'id_card_qr_generated_at')) {
                $table->timestamp('id_card_qr_generated_at')->nullable()->after('id_card_qr_code');
            }
        });

        DB::table('tb_student')
            ->where(function ($query): void {
                $query->whereNull('id_card_qr_code')
                    ->orWhere('id_card_qr_code', '');
            })
            ->orderBy('id')
            ->eachById(function ($student): void {
                DB::table('tb_student')
                    ->where('id', $student->id)
                    ->where(function ($query): void {
                        $query->whereNull('id_card_qr_code')
                            ->orWhere('id_card_qr_code', '');
                    })
                    ->update([
                        'id_card_qr_code' => Student::makeUniqueIdCardQrCode(),
                        'id_card_qr_generated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            if (Schema::hasColumn('tb_student', 'id_card_qr_code')) {
                $table->dropUnique(['id_card_qr_code']);
                $table->dropColumn('id_card_qr_code');
            }

            if (Schema::hasColumn('tb_student', 'id_card_qr_generated_at')) {
                $table->dropColumn('id_card_qr_generated_at');
            }
        });
    }
};
