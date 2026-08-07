<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Country;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CambodiaLocationSeeder extends Seeder
{
    private function cleanVillageText(?string $value): ?string
    {
        return is_null($value) ? null : str_replace('"', '', $value);
    }

    public function run(): void
    {
        $path = database_path('data/cambodia_geography_2025.json');
        if (!is_file($path)) throw new \RuntimeException('Cambodia geography data file is missing.');
        $records = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $country = Country::updateOrCreate(['country_code' => 'kh'], [
            'country_name_en' => 'Cambodia', 'country_name_kh' => 'កម្ពុជា', 'flag_path' => 'flags/cambodia.png', 'status' => true,
        ]);

        DB::transaction(function () use ($records, $country) {
            $provinces = []; $districts = []; $communes = [];
            foreach ($records as $row) {
                $province = $provinces[$row['province_code']] ??= Province::updateOrCreate(['province_code' => $row['province_code']], ['country_id' => $country->id, 'province_name_en' => $row['province_en'], 'province_name_kh' => $row['province_kh'], 'status' => true]);
                $district = $districts[$row['district_code']] ??= District::updateOrCreate(['district_code' => $row['district_code']], ['province_id' => $province->id, 'district_name_en' => $row['district_en'], 'district_name_kh' => $row['district_kh'], 'status' => true]);
                $commune = $communes[$row['commune_code']] ??= Commune::updateOrCreate(['commune_code' => $row['commune_code']], ['district_id' => $district->id, 'commune_name_en' => $row['commune_en'], 'commune_name_kh' => $row['commune_kh'], 'status' => true]);
                Village::updateOrCreate(
                    ['village_code' => $row['village_code']],
                    [
                        'commune_id' => $commune->id,
                        'village_name_en' => $this->cleanVillageText($row['village_en'] ?? null),
                        'village_name_kh' => $this->cleanVillageText($row['village_kh'] ?? null),
                        'status' => true,
                    ]
                );
            }
        });
    }
}
