<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_student_document_type', function (Blueprint $table) {
            $table->id(); $table->string('type_key', 80)->unique(); $table->string('name_en', 180); $table->string('name_kh', 180)->nullable(); $table->unsignedInteger('sort_order')->default(0); $table->boolean('status')->default(true)->index(); $table->timestamps();
        });
        foreach ([['birth-certificate', 'Birth Certificate'], ['passport', 'Passport'], ['identity-card', 'Identity Card'], ['medical-record', 'Medical Record'], ['previous-school-record', 'Previous School Record'], ['family-record', 'Family Record'], ['other', 'Other']] as $index => $item) DB::table('tb_student_document_type')->insert(['type_key' => $item[0], 'name_en' => $item[1], 'sort_order' => $index + 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('tb_student_document', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->after('student_id')->constrained('tb_student_document_type')->nullOnDelete(); $table->string('title', 180)->nullable()->after('document_type_id'); $table->text('description')->nullable()->after('expiry_date'); $table->string('file_path', 500)->nullable()->after('file_id'); $table->string('original_filename', 255)->nullable()->after('file_path'); $table->string('mime_type', 120)->nullable()->after('original_filename'); $table->unsignedBigInteger('file_size')->nullable()->after('mime_type'); $table->foreignId('uploaded_by')->nullable()->after('status')->constrained('users')->nullOnDelete(); $table->index(['student_id', 'document_type_id']);
        });
    }
    public function down(): void { Schema::table('tb_student_document', function (Blueprint $table) { $table->dropForeign(['document_type_id']); $table->dropForeign(['uploaded_by']); $table->dropColumn(['document_type_id','title','description','file_path','original_filename','mime_type','file_size','uploaded_by']); }); Schema::dropIfExists('tb_student_document_type'); }
};
