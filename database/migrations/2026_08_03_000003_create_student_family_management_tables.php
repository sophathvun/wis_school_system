<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_family', function (Blueprint $table) {
            $table->id();
            $table->string('family_number', 30)->unique();
            $table->string('family_name', 120)->nullable();
            $table->string('family_name_kh', 120)->nullable();
            $table->string('primary_phone', 50)->nullable();
            $table->string('primary_email', 150)->nullable();
            $table->text('address')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tb_family_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('tb_family')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name_en', 80);
            $table->string('last_name_en', 80);
            $table->string('first_name_kh', 80)->nullable();
            $table->string('last_name_kh', 80)->nullable();
            $table->string('relationship_type', 40);
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('has_pickup_authorization')->default(false);
            $table->boolean('has_portal_access')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['family_id', 'relationship_type']);
        });

        Schema::create('tb_family_student', function (Blueprint $table) {
            $table->foreignId('family_id')->constrained('tb_family')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->string('relationship_type', 40)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('has_pickup_authorization')->default(false);
            $table->boolean('has_portal_access')->default(false);
            $table->timestamps();
            $table->primary(['family_id', 'student_id']);
        });

        Schema::create('tb_student_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->string('contact_type', 40);
            $table->string('contact_value', 150);
            $table->boolean('is_primary')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'contact_type']);
        });

        Schema::create('tb_student_address', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->string('address_type', 40)->default('home');
            $table->string('line_1', 150)->nullable();
            $table->string('line_2', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'address_type']);
        });

        Schema::create('tb_student_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('document_number', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            // File storage is introduced in the next platform phase; keep this nullable reference compatible for now.
            $table->unsignedBigInteger('file_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'document_type']);
        });

        DB::table('tb_student')->select('family_number')->whereNotNull('family_number')->where('family_number', '!=', '')
            ->distinct()->orderBy('family_number')->get()->each(function ($row) {
                DB::table('tb_family')->insertOrIgnore([
                    'family_number' => $row->family_number,
                    'family_name' => $row->family_number,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::statement('INSERT IGNORE INTO tb_family_student (family_id, student_id, created_at, updated_at)
            SELECT f.id, s.id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM tb_family f INNER JOIN tb_student s ON s.family_number = f.family_number');
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_document');
        Schema::dropIfExists('tb_student_address');
        Schema::dropIfExists('tb_student_contact');
        Schema::dropIfExists('tb_family_student');
        Schema::dropIfExists('tb_family_member');
        Schema::dropIfExists('tb_family');
    }
};
