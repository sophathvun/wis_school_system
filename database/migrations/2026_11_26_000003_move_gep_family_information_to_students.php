<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('tb_gep_student')->orderBy('id')->chunkById(200,function($students){
            foreach($students as $student){
                $profile=json_decode($student->profile??'{}',true)??[];
                if(array_key_exists('parent_information',$profile))continue;
                $parents=[];
                foreach(DB::table('tb_gep_enrollment')->where('gep_student_id',$student->id)->orderBy('id')->get(['parent_information','guardian_name','guardian_phone']) as $enrollment){
                    $snapshot=json_decode($enrollment->parent_information??'null',true)??[];
                    if(!$snapshot&&$enrollment->guardian_name)$snapshot=['guardian'=>['name_en'=>$enrollment->guardian_name,'phone'=>$enrollment->guardian_phone]];
                    foreach($snapshot as $relationship=>$fields){
                        foreach($fields as $field=>$value)if($value!==null&&$value!=='')$parents[$relationship][$field]=$value;
                    }
                }
                if($parents){$profile['parent_information']=$parents;DB::table('tb_gep_student')->where('id',$student->id)->update(['profile'=>json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
            }
        });
    }
    public function down(): void
    {
        // Preserve family data and later corrections when rolling back code.
    }
};
