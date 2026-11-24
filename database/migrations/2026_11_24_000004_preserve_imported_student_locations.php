<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student',function(Blueprint $t){
            foreach(['province','district','commune','village'] as $level)foreach(['en','kh'] as $language)$t->text('address_'.$level.'_'.$language)->nullable();
            $t->json('imported_location_data')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('tb_student',function(Blueprint $t){
            foreach(['province','district','commune','village'] as $level)foreach(['en','kh'] as $language)$t->dropColumn('address_'.$level.'_'.$language);
            $t->dropColumn('imported_location_data');
        });
    }
};
