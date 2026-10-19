<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_session_link',function(Blueprint $t){
            $t->id();$t->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $t->foreignId('exam_center_id')->constrained('tb_scholarship_exam_center')->cascadeOnDelete();
            $t->json('session_ids');$t->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('tb_scholarship_session_link');}
};
