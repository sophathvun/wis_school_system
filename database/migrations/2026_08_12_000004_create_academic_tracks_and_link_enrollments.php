<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tb_academic_track')) {
            Schema::create('tb_academic_track', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
                $table->string('name_en', 120);
                $table->string('name_kh', 120)->nullable();
                $table->string('code', 50)->nullable()->unique();
                $table->string('stream_type', 40)->default('science');
                $table->string('language', 30)->default('khmer');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['grade_id', 'status'], 'academic_track_grade_status_index');
            });
        }

        $grade12Id = DB::table('tb_grade')
            ->where('grade', 'like', '%12%')
            ->orWhere('grade_short_name', 'like', '%12%')
            ->orWhere('grade_order', '12')
            ->orderBy('id')
            ->value('id');

        foreach ([
            ['Science - Khmer', 'វិទ្យាសាស្ត្រ - ខ្មែរ', 'SCI-KH', 'science', 'khmer'],
            ['Science - English', 'វិទ្យាសាស្ត្រ - អង់គ្លេស', 'SCI-EN', 'science', 'english'],
            ['Social Science - Khmer', 'សង្គមវិទ្យា - ខ្មែរ', 'SOC-KH', 'social_science', 'khmer'],
            ['Social Science - English', 'សង្គមវិទ្យា - អង់គ្លេស', 'SOC-EN', 'social_science', 'english'],
        ] as [$nameEn, $nameKh, $code, $stream, $language]) {
            DB::table('tb_academic_track')->updateOrInsert(
                ['code' => $code],
                [
                    'grade_id' => $grade12Id,
                    'name_en' => $nameEn,
                    'name_kh' => $nameKh,
                    'stream_type' => $stream,
                    'language' => $language,
                    'status' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_student_enrollment', 'academic_track_id')) {
                $table->foreignId('academic_track_id')->nullable()->after('class_id')->constrained('tb_academic_track')->nullOnDelete();
                $table->index(['academic_year_id', 'grade_id', 'academic_track_id'], 'enrollment_year_grade_track_index');
            }
        });

        if (Schema::hasTable('tb_student_enrollment_history')) {
            Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_student_enrollment_history', 'academic_track_id')) {
                    $table->foreignId('academic_track_id')->nullable()->after('class_id')->constrained('tb_academic_track')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tb_student_enrollment_history') && Schema::hasColumn('tb_student_enrollment_history', 'academic_track_id')) {
            Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
                $table->dropConstrainedForeignId('academic_track_id');
            });
        }

        if (Schema::hasColumn('tb_student_enrollment', 'academic_track_id')) {
            Schema::table('tb_student_enrollment', function (Blueprint $table) {
                $table->dropIndex('enrollment_year_grade_track_index');
                $table->dropConstrainedForeignId('academic_track_id');
            });
        }

        Schema::dropIfExists('tb_academic_track');
    }
};
