<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_exam_center',function(Blueprint $table){
            $table->string('location',80)->default('Phnom Penh');
        });
        // Preserve locations already saved through Seat Plan Print Settings.
        DB::table('tb_scholarship_seat_print_setting')->select('id','exam_center_id','signing_location')->orderBy('id')->chunkById(200,function($settings){
            foreach($settings as $setting){
                $location=trim((string)$setting->signing_location);
                if($location!=='')DB::table('tb_scholarship_exam_center')->where('id',$setting->exam_center_id)->update(['location'=>$location]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_exam_center',fn(Blueprint $table)=>$table->dropColumn('location'));
    }
};
