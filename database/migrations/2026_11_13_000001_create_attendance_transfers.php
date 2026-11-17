<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('filename');
            $table->string('status')->default('preview');
            $table->timestamps();
        });
        Schema::create('attendance_import_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->unsignedInteger('csv_row');
            $table->json('source');
            $table->json('normalized')->nullable();
            $table->string('outcome')->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('mark_id')->nullable();
            $table->index(['batch_id', 'outcome']);
        });
        foreach (['transfer'=>'Submenu: Attendance Import / Export', 'import'=>'Import historical daily attendance', 'export'=>'Export daily attendance CSV'] as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'attendance.'.$action], ['module'=>'attendance','action'=>$action,'name'=>$name,'updated_at'=>now(),'created_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_rows');
        Schema::dropIfExists('attendance_import_batches');
        DB::table('access_permissions')->whereIn('code',['attendance.transfer','attendance.import','attendance.export'])->delete();
    }
};
