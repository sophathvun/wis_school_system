<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up():void
    {
        DB::transaction(function(){
            $rows=DB::table('tb_gep_academic_year')->orderBy('id')->lockForUpdate()->get();
            $targets=[];$updates=[];
            foreach($rows as $row){
                $target=$row->academic_year;
                if(preg_match('/^([1-9]\d{3})-([1-9]\d{3})$/',$target,$matches) && (int)$matches[2]===(int)$matches[1]+1)$target=$matches[1];
                if(!preg_match('/^[1-9]\d{3}$/',$target))throw new RuntimeException('Invalid GEP academic year: '.$row->academic_year);
                if(isset($targets[$target]))throw new RuntimeException('Conflicting GEP academic years for '.$target.'. Resolve the duplicate before migrating.');
                $targets[$target]=true;
                if($target!==$row->academic_year)$updates[$row->id]=$target;
            }
            foreach($updates as $id=>$target)DB::table('tb_gep_academic_year')->where('id',$id)->update(['academic_year'=>$target]);
        });
    }
    public function down():void
    {
        // Single years cannot safely be distinguished from converted ranges.
    }
};
