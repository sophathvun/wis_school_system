<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_country', function (Blueprint $table) {
            $table->id();
            $table->string('country_name_en', 100);
            $table->string('country_name_kh', 100)->nullable();
            $table->string('country_code', 10)->nullable()->unique();
            $table->string('flag_path', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        foreach ([
            ['Cambodia', 'កម្ពុជា', 'kh', 'flags/cambodia.svg'],
            ['Thailand', 'ថៃ', 'th', 'flags/thailand.svg'],
            ['Laos', 'ឡាវ', 'la', 'flags/laos.svg'],
            ['Vietnam', 'វៀតណាម', 'vn', 'flags/vietnam.svg'],
            ['India', 'ឥណ្ឌា', 'in', 'flags/india.svg'],
            ['United Kingdom', 'ចក្រភពអង់គ្លេស', 'gb', 'flags/uk.svg'],
            ['United States', 'សហរដ្ឋអាមេរិក', 'us', 'flags/usa.svg'],
        ] as $country) {
            DB::table('tb_country')->insert([
                'country_name_en' => $country[0], 'country_name_kh' => $country[1],
                'country_code' => $country[2], 'flag_path' => $country[3],
                'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $createLocation = function (string $table, string $parent): void {
            Schema::create($table, function (Blueprint $blueprint) use ($parent) {
                $blueprint->id();
                $blueprint->foreignId($parent)->constrained('tb_' . str_replace('_id', '', $parent))->cascadeOnDelete();
                $blueprint->string(str_replace('_id', '', $parent) . '_name_en', 100)->nullable();
                $blueprint->string(str_replace('_id', '', $parent) . '_name_kh', 100)->nullable();
                $blueprint->string('name_en', 100);
                $blueprint->string('name_kh', 100)->nullable();
                $blueprint->boolean('status')->default(true);
                $blueprint->timestamps();
            });
        };

        // Explicit schemas keep column names readable and avoid copying parent labels.
        Schema::create('tb_province', function (Blueprint $table) {
            $table->id(); $table->foreignId('country_id')->constrained('tb_country')->cascadeOnDelete();
            $table->string('province_name_en', 100); $table->string('province_name_kh', 100)->nullable();
            $table->boolean('status')->default(true); $table->timestamps();
        });
        Schema::create('tb_district', function (Blueprint $table) {
            $table->id(); $table->foreignId('province_id')->constrained('tb_province')->cascadeOnDelete();
            $table->string('district_name_en', 100); $table->string('district_name_kh', 100)->nullable();
            $table->boolean('status')->default(true); $table->timestamps();
        });
        Schema::create('tb_commune', function (Blueprint $table) {
            $table->id(); $table->foreignId('district_id')->constrained('tb_district')->cascadeOnDelete();
            $table->string('commune_name_en', 100); $table->string('commune_name_kh', 100)->nullable();
            $table->boolean('status')->default(true); $table->timestamps();
        });
        Schema::create('tb_village', function (Blueprint $table) {
            $table->id(); $table->foreignId('commune_id')->constrained('tb_commune')->cascadeOnDelete();
            $table->string('village_name_en', 100); $table->string('village_name_kh', 100)->nullable();
            $table->boolean('status')->default(true); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_village'); Schema::dropIfExists('tb_commune');
        Schema::dropIfExists('tb_district'); Schema::dropIfExists('tb_province');
        Schema::dropIfExists('tb_country');
    }
};
