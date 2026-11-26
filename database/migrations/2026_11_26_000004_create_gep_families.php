<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasTable('tb_gep_family'))Schema::create('tb_gep_family',function(Blueprint $table){
            $table->id();$table->string('family_number',30)->unique();$table->json('parent_information')->nullable();$table->json('history')->nullable();$table->timestamps();
        });
        DB::table('tb_gep_student')->orderBy('updated_at')->orderBy('id')->chunk(200,function($students){
            foreach($students as $student){
                $profile=json_decode($student->profile??'{}',true)??[];$number=trim($profile['family_number']??'');if($number==='')continue;
                DB::table('tb_gep_family')->insertOrIgnore(['family_number'=>$number,'parent_information'=>'[]','created_at'=>now(),'updated_at'=>now()]);
                $family=DB::table('tb_gep_family')->where('family_number',$number)->first();
                $savedHistory=json_decode($family->history??'[]',true)??[];
                if(collect($savedHistory)->contains(fn($entry)=>($entry['action']??null)!=='migrated'))continue;
                $before=json_decode($family->parent_information??'[]',true)??[];$parents=$before;
                foreach($profile['parent_information']??[] as $relationship=>$fields)foreach($fields as $field=>$value)if($value!==null&&$value!=='')$parents[$relationship][$field]=$value;
                if($parents!==$before){
                    $history=$savedHistory;$history[]=['before'=>$before,'after'=>$parents,'source_student_id'=>$student->id,'action'=>'migrated','changed_at'=>now()->toIso8601String()];
                    DB::table('tb_gep_family')->where('id',$family->id)->update(['parent_information'=>json_encode($parents,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'history'=>json_encode($history,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now()]);
                }
            }
        });
    }
    public function down(): void
    {
        // Shared family details are retained when rolling back application code.
    }
};
