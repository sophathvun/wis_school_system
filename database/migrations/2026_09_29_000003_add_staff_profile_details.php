<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->string('nationality', 80)->nullable()->after('gender');
            $table->string('marital_status', 40)->nullable()->after('date_of_birth');
            $table->text('current_address')->nullable()->after('email');
            $table->string('emergency_contact_name')->nullable()->after('current_address');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship', 80)->nullable()->after('emergency_contact_phone');
        });

        Schema::create('hrm_staff_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('hrm_staff')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('position')->nullable();
            $table->string('employment_type', 80)->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('responsibilities')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'started_on']);
        });

        Schema::create('hrm_staff_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('hrm_staff')->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('degree')->nullable();
            $table->string('field_of_study')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('grade_or_result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_staff_educations');
        Schema::dropIfExists('hrm_staff_experiences');

        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->dropColumn([
                'nationality',
                'marital_status',
                'current_address',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
            ]);
        });
    }
};
