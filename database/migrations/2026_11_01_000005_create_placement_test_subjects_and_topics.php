<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_placement_test_subject', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en', 160);
            $table->string('name_kh', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tb_placement_test_subject_grade', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained('tb_placement_test_subject')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('tb_grade')->cascadeOnDelete();
            $table->primary(['subject_id', 'grade_id'], 'placement_subject_grade_primary');
        });

        Schema::create('tb_placement_test_topic', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('subject_id')->constrained('tb_placement_test_subject')->restrictOnDelete();
            $table->string('name_en', 180);
            $table->string('name_kh', 180);
            $table->text('description')->nullable();
            $table->string('difficulty', 20)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_id', 'status', 'display_order'], 'placement_topic_filter_index');
        });

        Schema::create('tb_placement_test_topic_grade', function (Blueprint $table) {
            $table->foreignId('topic_id')->constrained('tb_placement_test_topic')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('tb_grade')->cascadeOnDelete();
            $table->primary(['topic_id', 'grade_id'], 'placement_topic_grade_primary');
        });

        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('grade_id')->constrained('tb_placement_test_subject')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->after('subject_id')->constrained('tb_placement_test_topic')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropConstrainedForeignId('subject_id');
        });
        Schema::dropIfExists('tb_placement_test_topic_grade');
        Schema::dropIfExists('tb_placement_test_topic');
        Schema::dropIfExists('tb_placement_test_subject_grade');
        Schema::dropIfExists('tb_placement_test_subject');
    }
};
