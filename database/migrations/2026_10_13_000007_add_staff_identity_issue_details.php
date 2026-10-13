<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->string('identity_document_name')->nullable();
            $table->date('identity_document_issued_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->dropColumn(['identity_document_name','identity_document_issued_on']);
        });
    }
};
