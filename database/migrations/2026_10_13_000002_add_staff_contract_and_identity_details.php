<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->string('secondary_phone', 50)->nullable();
            $table->string('work_email')->nullable();
            $table->string('identity_document_type', 30)->nullable();
            $table->string('identity_document_number', 100)->nullable();
            $table->date('identity_document_expires_on')->nullable();
            $table->text('emergency_contact_address')->nullable();
            $table->string('contract_type', 30)->nullable();
            $table->string('contract_number', 100)->nullable();
            $table->date('contract_started_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->date('probation_ends_on')->nullable();
            $table->foreignId('reports_to_staff_id')->nullable()->constrained('hrm_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_staff_id');
            $table->dropColumn(['secondary_phone', 'work_email', 'identity_document_type', 'identity_document_number',
                'identity_document_expires_on', 'emergency_contact_address', 'contract_type', 'contract_number',
                'contract_started_on', 'contract_ends_on', 'probation_ends_on']);
        });
    }
};
