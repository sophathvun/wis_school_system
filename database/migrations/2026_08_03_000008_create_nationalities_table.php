<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_nationality', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('tb_country')->nullOnDelete();
            $table->string('nationality_name_en', 100)->unique();
            $table->string('nationality_name_kh', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        $countryId = DB::table('tb_country')->where('country_name_en', 'Cambodia')->value('id');
        DB::table('tb_nationality')->insert([
            'country_id' => $countryId,
            'nationality_name_en' => 'Cambodian',
            'nationality_name_kh' => 'កម្ពុជា',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->foreignId('nationality_id')->nullable()->after('nationality_country_id')->constrained('tb_nationality')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropForeign(['nationality_id']);
            $table->dropColumn('nationality_id');
        });
        Schema::dropIfExists('tb_nationality');
    }
};
