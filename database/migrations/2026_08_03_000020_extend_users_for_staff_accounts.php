<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->nullable()->unique()->after('name');
            $table->foreignId('department_id')->nullable()->after('active_campus_id')->constrained('access_departments')->nullOnDelete();
            $table->string('photo_path')->nullable()->after('department_id');
            $table->string('login_identifier', 10)->default('username')->after('photo_path');
            $table->boolean('is_global')->default(false)->after('login_identifier');
        });

        DB::table('users')->whereNull('username')->get(['id', 'email'])->each(function ($user) {
            $base = preg_replace('/[^A-Za-z0-9_.-]/', '.', (string) str($user->email)->before('@')) ?: 'staff';
            $username = $base;
            $counter = 1;
            while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $base.'.'.$counter++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'department_id', 'photo_path', 'login_identifier', 'is_global']);
        });
    }
};
