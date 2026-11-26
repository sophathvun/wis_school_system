<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GepStudentIdAllocator
{
    public const INITIAL_SEQUENCE=190000;

    public function highestImportedSequence(): int
    {
        $query=DB::table('tb_gep_student')->whereRaw('LENGTH(student_identifier) = 8');
        if(DB::getDriverName()==='sqlite')$query->whereRaw("student_identifier NOT GLOB '*[^0-9]*'");
        else $query->whereRaw("student_identifier REGEXP '^[0-9]{8}$'");
        $cast=DB::getDriverName()==='sqlite'?'INTEGER':'UNSIGNED';
        return max(self::INITIAL_SEQUENCE,(int)$query->selectRaw("MAX(CAST(SUBSTR(student_identifier, 3, 6) AS {$cast})) AS highest")->value('highest'));
    }

    private function lockedSequence(): object
    {
        return DB::table('tb_gep_student_id_sequence')->where('id',1)->lockForUpdate()->first()
            ?? throw new \RuntimeException('GEP Student ID counter is missing. Run the database migrations.');
    }

    public function synchronize(): int
    {
        return DB::transaction(function(){
            $last=max((int)$this->lockedSequence()->last_number,$this->highestImportedSequence());
            DB::table('tb_gep_student_id_sequence')->where('id',1)->update(['last_number'=>$last]);
            return $last;
        });
    }

    /** Reserve imported/manual IDs even before their profile insert completes. */
    public function observeIdentifier(?string $identifier): void
    {
        if(!preg_match('/^[0-9]{8}$/D',$identifier??''))return;
        $number=(int)substr($identifier,2);
        if($number<=self::INITIAL_SEQUENCE)return;
        DB::transaction(function()use($number){
            $last=(int)$this->lockedSequence()->last_number;
            if($number>$last)DB::table('tb_gep_student_id_sequence')->where('id',1)->update(['last_number'=>$number]);
        });
    }

    public function allocate(string $academicYear): string
    {
        if(DB::transactionLevel()===0)throw new \LogicException('Allocate GEP Student IDs inside the enrollment transaction.');
        if(!preg_match('/^[1-9][0-9]{3}$/D',$academicYear))throw ValidationException::withMessages(['academic_year_id'=>'Select a valid four-digit GEP academic year.']);
        $next=max((int)$this->lockedSequence()->last_number,$this->highestImportedSequence())+1;
        if($next>999999)throw ValidationException::withMessages(['student_identifier'=>'The six-digit GEP Student ID sequence is full.']);
        DB::table('tb_gep_student_id_sequence')->where('id',1)->update(['last_number'=>$next]);
        return substr($academicYear,-2).str_pad((string)$next,6,'0',STR_PAD_LEFT);
    }
}
