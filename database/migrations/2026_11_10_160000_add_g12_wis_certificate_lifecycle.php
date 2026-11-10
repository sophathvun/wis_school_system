<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_g12_certificate', function (Blueprint $table) {
            // Existing school certificates retain their validity and numbers.
            $table->string('status', 32)->default('valid')->index();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 500)->nullable();
        });
        Schema::create('tb_g12_wis_certificate_event', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('academic_year_id')->index();
            $table->unsignedBigInteger('graduation_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 40);
            $table->json('details');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_g12_wis_certificate_event');
        Schema::table('tb_g12_certificate', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status','printed_at','released_at','voided_at','voided_by','void_reason']);
        });
    }
};
