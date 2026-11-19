<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_gep_class', fn (Blueprint $table) => $table->string('short_name',30)->nullable()->change());
    }

    public function down(): void
    {
        // Preserve classes created without the retired abbreviation field.
    }
};
