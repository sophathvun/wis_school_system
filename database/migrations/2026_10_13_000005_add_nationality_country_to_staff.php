<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', fn(Blueprint $table) => $table->foreignId('nationality_country_id')->nullable()->constrained('tb_country')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('hrm_staff', fn(Blueprint $table) => $table->dropConstrainedForeignId('nationality_country_id'));
    }
};
