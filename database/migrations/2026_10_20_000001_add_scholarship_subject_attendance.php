<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_score',fn(Blueprint $table)=>$table->boolean('is_absent')->default(false));
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_score',fn(Blueprint $table)=>$table->dropColumn('is_absent'));
    }
};
