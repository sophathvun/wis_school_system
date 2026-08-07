<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->json('reasons')->nullable()->after('reason');
            $table->text('reason_kh')->nullable()->after('reasons');
            $table->string('other_reason_en', 500)->nullable()->after('reason_kh');
            $table->string('other_reason_kh', 500)->nullable()->after('other_reason_en');
            $table->string('new_school', 200)->nullable()->after('other_reason_kh');
            $table->string('new_school_address', 500)->nullable()->after('new_school');
            $table->string('dropout_type', 30)->nullable()->after('new_school_address');
            $table->text('additional_comments')->nullable()->after('dropout_type');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->dropColumn(['reasons', 'reason_kh', 'other_reason_en', 'other_reason_kh', 'new_school', 'new_school_address', 'dropout_type', 'additional_comments']);
        });
    }
};
