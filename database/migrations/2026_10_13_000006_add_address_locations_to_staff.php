<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            foreach (['country','province','district','commune','village'] as $level) {
                $table->foreignId('address_'.$level.'_id')->nullable()->constrained('tb_'.$level)->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            foreach (['village','commune','district','province','country'] as $level) {
                $table->dropConstrainedForeignId('address_'.$level.'_id');
            }
        });
    }
};
