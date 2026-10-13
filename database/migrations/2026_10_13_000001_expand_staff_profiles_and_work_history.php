<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            foreach (['place_of_birth_en','place_of_birth_kh','nationality_kh','house_number_en','house_number_kh','street_en','street_kh',
                'village_en','village_kh','commune_en','commune_kh','district_en','district_kh','province_en','province_kh'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('current_address_kh')->nullable();
            $table->text('employment_end_reason')->nullable();
            foreach (['session_1_in','session_1_out','session_2_in','session_2_out'] as $field) $table->string($field,5)->nullable();
            $table->json('work_schedule')->nullable();
        });
        Schema::table('hrm_staff_educations', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->unsignedSmallInteger('graduated_year')->nullable();
        });
        Schema::create('hrm_staff_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('hrm_staff')->cascadeOnDelete();
            $table->string('change_type',40);
            $table->date('effective_on');
            $table->text('reason')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->timestamps();
            $table->index(['staff_id','effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_staff_work_histories');
        Schema::table('hrm_staff_educations', fn(Blueprint $table) => $table->dropColumn(['country','graduated_year']));
        Schema::table('hrm_staff', fn(Blueprint $table) => $table->dropColumn([
            'place_of_birth_en','place_of_birth_kh','nationality_kh','house_number_en','house_number_kh','street_en','street_kh',
            'village_en','village_kh','commune_en','commune_kh','district_en','district_kh','province_en','province_kh',
            'current_address_kh','employment_end_reason','session_1_in','session_1_out','session_2_in','session_2_out','work_schedule',
        ]));
    }
};
