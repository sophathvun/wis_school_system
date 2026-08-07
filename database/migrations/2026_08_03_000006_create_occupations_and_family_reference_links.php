<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_occupation', function (Blueprint $table) {
            $table->id();
            $table->string('occupation_name_en', 120)->unique();
            $table->string('occupation_name_kh', 120)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->foreignId('nationality_country_id')->nullable()->after('nationality_kh')->constrained('tb_country')->nullOnDelete();
            $table->foreignId('occupation_id')->nullable()->after('occupation_kh')->constrained('tb_occupation')->nullOnDelete();
        });

        foreach ([
            ['Teacher', 'គ្រូបង្រៀន'],
            ['Doctor', 'វេជ្ជបណ្ឌិត'],
            ['Nurse', 'គិលានុបដ្ឋាយិកា'],
            ['Engineer', 'វិស្វករ'],
            ['Business Owner', 'ម្ចាស់អាជីវកម្ម'],
            ['Farmer', 'កសិករ'],
            ['Government Officer', 'មន្ត្រីរាជការ'],
            ['Employee', 'និយោជិត'],
            ['Other', 'ផ្សេងៗ'],
        ] as [$english, $khmer]) {
            DB::table('tb_occupation')->insert([
                'occupation_name_en' => $english,
                'occupation_name_kh' => $khmer,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropForeign(['occupation_id']);
            $table->dropForeign(['nationality_country_id']);
            $table->dropColumn(['occupation_id', 'nationality_country_id']);
        });
        Schema::dropIfExists('tb_occupation');
    }
};
