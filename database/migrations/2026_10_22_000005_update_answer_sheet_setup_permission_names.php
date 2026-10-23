<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach([
            'answer-paper'=>'Set up shared grade / subject papers',
            'answer-key'=>'Edit shared draft answer keys',
            'answer-key-approve'=>'Approve shared answer key revisions',
        ] as $action=>$name)DB::table('access_permissions')->where('code','western-scholarship.'.$action)->update(['name'=>$name,'updated_at'=>now()]);
    }
    public function down(): void
    {
        foreach([
            'answer-paper'=>'Set up grade / subject papers (Central only)',
            'answer-key'=>'Edit draft answer keys (Central only)',
            'answer-key-approve'=>'Approve answer key revisions (Central only)',
        ] as $action=>$name)DB::table('access_permissions')->where('code','western-scholarship.'.$action)->update(['name'=>$name,'updated_at'=>now()]);
    }
};
