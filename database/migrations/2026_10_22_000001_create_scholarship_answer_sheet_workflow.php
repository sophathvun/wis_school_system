<?php

use App\Support\ScholarshipPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_paper', function (Blueprint $t) {
            $t->json('draft_answers')->nullable();
            $t->unsignedInteger('version')->default(1);
        });
        Schema::create('tb_scholarship_answer_key', function (Blueprint $t) {
            $t->id(); $t->foreignId('paper_id')->constrained('tb_scholarship_paper')->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->json('answers');
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at'); $t->timestamps();
            $t->unique(['paper_id', 'revision'], 'sch_answer_key_revision');
        });
        Schema::create('tb_scholarship_answer_sheet', function (Blueprint $t) {
            $t->id(); $t->foreignId('paper_id')->constrained('tb_scholarship_paper')->restrictOnDelete();
            $t->foreignId('candidate_id')->constrained('tb_scholarship_candidate')->restrictOnDelete();
            $t->string('token', 24)->unique(); $t->string('fingerprint', 64); $t->json('snapshot');
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->unique(['paper_id','candidate_id','fingerprint'], 'sch_answer_sheet_identity');
        });
        Schema::create('tb_scholarship_answer_scan', function (Blueprint $t) {
            $t->id(); $t->foreignId('sheet_id')->constrained('tb_scholarship_answer_sheet')->restrictOnDelete();
            $t->foreignId('key_id')->constrained('tb_scholarship_answer_key')->restrictOnDelete();
            $t->string('image_path'); $t->string('original_path'); $t->string('image_hash',64);
            $t->json('detected'); $t->json('responses')->nullable(); $t->json('flags'); $t->json('confidence');
            $t->decimal('score',10,2)->nullable(); $t->string('status',20)->default('pending');
            $t->unsignedInteger('candidate_version'); $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('verified_at')->nullable(); $t->text('reason')->nullable();
            $t->unsignedBigInteger('source_scan_id')->nullable(); $t->timestamps();
            $t->index(['sheet_id','status'], 'sch_answer_scan_status');
        });
        foreach (ScholarshipPermissions::catalog() as $action=>$name) {
            if ($action === 'tab-answer-sheets' || str_starts_with($action,'answer-')) DB::table('access_permissions')->updateOrInsert(
                ['code'=>'western-scholarship.'.$action], ['module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_scholarship_answer_scan'); Schema::dropIfExists('tb_scholarship_answer_sheet'); Schema::dropIfExists('tb_scholarship_answer_key');
        Schema::table('tb_scholarship_paper', fn (Blueprint $t)=>$t->dropColumn(['draft_answers','version']));
        DB::table('access_permissions')->where('module','western-scholarship')->where(fn($q)=>$q->where('action','tab-answer-sheets')->orWhere('action','like','answer-%'))->delete();
    }
};
