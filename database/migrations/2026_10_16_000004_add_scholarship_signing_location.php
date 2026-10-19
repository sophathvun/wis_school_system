<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_seat_print_setting',function(Blueprint $table){
            $table->string('signing_location',80)->default('Phnom Penh');
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_seat_print_setting',function(Blueprint $table){
            $table->dropColumn('signing_location');
        });
    }
};
