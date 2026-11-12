<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach(['k3','g9','g12'] as $level) Schema::table('tb_'.$level.'_certificate',fn(Blueprint $table)=>$table->json('historical_import')->nullable());
    }
    public function down(): void {
        foreach(['k3','g9','g12'] as $level) {
            if(\Illuminate\Support\Facades\DB::table('tb_'.$level.'_certificate')->whereNotNull('historical_import')->exists()) throw new RuntimeException('Historical issued certificate provenance must be preserved.');
        }
        foreach(['k3','g9','g12'] as $level) Schema::table('tb_'.$level.'_certificate',fn(Blueprint $table)=>$table->dropColumn('historical_import'));
    }
};
