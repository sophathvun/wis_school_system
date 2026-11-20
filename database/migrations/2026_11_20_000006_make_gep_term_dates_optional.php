<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void
    {
        Schema::table('tb_gep_term_assignment',function(Blueprint $t){
            $t->date('start_date')->nullable()->change();
            $t->date('end_date')->nullable()->change();
        });
    }
    public function down():void
    {
        // Preserve historical terms whose dates are unknown.
    }
};
