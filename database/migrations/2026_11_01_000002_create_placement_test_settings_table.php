<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_placement_test_setting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->unique()->constrained('tb_school_info')->cascadeOnDelete();
            $table->boolean('online_enabled')->default(true);
            $table->boolean('paper_enabled')->default(true);
            $table->boolean('manual_enabled')->default(true);
            $table->boolean('scan_enabled')->default(true);
            $table->boolean('show_result_to_candidate')->default(true);
            $table->boolean('enrollment_handoff_enabled')->default(true);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->decimal('default_max_score', 8, 2)->default(100);
            $table->decimal('default_pass_percentage', 5, 2)->default(60);
            $table->unsignedTinyInteger('online_grace_minutes')->default(2);
            $table->text('candidate_instructions')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_placement_test_setting');
    }
};
