<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $records = [
            'Afghanistan|Afghan', 'Albania|Albanian', 'Algeria|Algerian', 'Andorra|Andorran', 'Angola|Angolan', 'Antigua and Barbuda|Antiguan and Barbudan', 'Argentina|Argentine', 'Armenia|Armenian', 'Australia|Australian', 'Austria|Austrian', 'Azerbaijan|Azerbaijani',
            'Bahamas|Bahamian', 'Bahrain|Bahraini', 'Bangladesh|Bangladeshi', 'Barbados|Barbadian', 'Belarus|Belarusian', 'Belgium|Belgian', 'Belize|Belizean', 'Benin|Beninese', 'Bhutan|Bhutanese', 'Bolivia|Bolivian', 'Bosnia and Herzegovina|Bosnian', 'Botswana|Botswanan', 'Brazil|Brazilian', 'Brunei|Bruneian', 'Bulgaria|Bulgarian', 'Burkina Faso|Burkinabe', 'Burundi|Burundian',
            'Cabo Verde|Cape Verdean', 'Cambodia|Cambodian', 'Cameroon|Cameroonian', 'Canada|Canadian', 'Central African Republic|Central African', 'Chad|Chadian', 'Chile|Chilean', 'China|Chinese', 'Colombia|Colombian', 'Comoros|Comorian', 'Congo|Congolese', 'Costa Rica|Costa Rican', 'Croatia|Croatian', 'Cuba|Cuban', 'Cyprus|Cypriot', 'Czechia|Czech',
            'Democratic Republic of the Congo|Congolese', 'Denmark|Danish', 'Djibouti|Djiboutian', 'Dominica|Dominican', 'Dominican Republic|Dominican', 'Ecuador|Ecuadorian', 'Egypt|Egyptian', 'El Salvador|Salvadoran', 'Equatorial Guinea|Equatorial Guinean', 'Eritrea|Eritrean', 'Estonia|Estonian', 'Eswatini|Swazi', 'Ethiopia|Ethiopian',
            'Fiji|Fijian', 'Finland|Finnish', 'France|French', 'Gabon|Gabonese', 'Gambia|Gambian', 'Georgia|Georgian', 'Germany|German', 'Ghana|Ghanaian', 'Greece|Greek', 'Grenada|Grenadian', 'Guatemala|Guatemalan', 'Guinea|Guinean', 'Guinea-Bissau|Bissau-Guinean', 'Guyana|Guyanese',
            'Haiti|Haitian', 'Honduras|Honduran', 'Hungary|Hungarian', 'Iceland|Icelandic', 'India|Indian', 'Indonesia|Indonesian', 'Iran|Iranian', 'Iraq|Iraqi', 'Ireland|Irish', 'Israel|Israeli', 'Italy|Italian', 'Jamaica|Jamaican', 'Japan|Japanese', 'Jordan|Jordanian',
            'Kazakhstan|Kazakhstani', 'Kenya|Kenyan', 'Kiribati|I-Kiribati', 'Kuwait|Kuwaiti', 'Kyrgyzstan|Kyrgyzstani', 'Laos|Lao', 'Latvia|Latvian', 'Lebanon|Lebanese', 'Lesotho|Basotho', 'Liberia|Liberian', 'Libya|Libyan', 'Liechtenstein|Liechtensteiner', 'Lithuania|Lithuanian', 'Luxembourg|Luxembourgish',
            'Madagascar|Malagasy', 'Malawi|Malawian', 'Malaysia|Malaysian', 'Maldives|Maldivian', 'Mali|Malian', 'Malta|Maltese', 'Marshall Islands|Marshallese', 'Mauritania|Mauritanian', 'Mauritius|Mauritian', 'Mexico|Mexican', 'Micronesia|Micronesian', 'Moldova|Moldovan', 'Monaco|Monegasque', 'Mongolia|Mongolian', 'Montenegro|Montenegrin', 'Morocco|Moroccan', 'Mozambique|Mozambican', 'Myanmar|Burmese',
            'Namibia|Namibian', 'Nauru|Nauruan', 'Nepal|Nepali', 'Netherlands|Dutch', 'New Zealand|New Zealander', 'Nicaragua|Nicaraguan', 'Niger|Nigerien', 'Nigeria|Nigerian', 'North Korea|North Korean', 'North Macedonia|Macedonian', 'Norway|Norwegian', 'Oman|Omani', 'Pakistan|Pakistani', 'Palau|Palauan', 'Palestine|Palestinian', 'Panama|Panamanian', 'Papua New Guinea|Papua New Guinean', 'Paraguay|Paraguayan', 'Peru|Peruvian', 'Philippines|Filipino', 'Poland|Polish', 'Portugal|Portuguese',
            'Qatar|Qatari', 'Romania|Romanian', 'Russia|Russian', 'Rwanda|Rwandan', 'Saint Kitts and Nevis|Kittitian and Nevisian', 'Saint Lucia|Saint Lucian', 'Saint Vincent and the Grenadines|Vincentian', 'Samoa|Samoan', 'San Marino|Sammarinese', 'Sao Tome and Principe|Sao Tomean', 'Saudi Arabia|Saudi Arabian', 'Senegal|Senegalese', 'Serbia|Serbian', 'Seychelles|Seychellois', 'Sierra Leone|Sierra Leonean', 'Singapore|Singaporean', 'Slovakia|Slovak', 'Slovenia|Slovenian', 'Solomon Islands|Solomon Islander', 'Somalia|Somali', 'South Africa|South African', 'South Korea|South Korean', 'South Sudan|South Sudanese', 'Spain|Spanish', 'Sri Lanka|Sri Lankan', 'Sudan|Sudanese', 'Suriname|Surinamese', 'Sweden|Swedish', 'Switzerland|Swiss', 'Syria|Syrian',
            'Taiwan|Taiwanese', 'Tajikistan|Tajikistani', 'Tanzania|Tanzanian', 'Thailand|Thai', 'Timor-Leste|Timorese', 'Togo|Togolese', 'Tonga|Tongan', 'Trinidad and Tobago|Trinidadian and Tobagonian', 'Tunisia|Tunisian', 'Turkey|Turkish', 'Turkmenistan|Turkmen', 'Tuvalu|Tuvaluan', 'Uganda|Ugandan', 'Ukraine|Ukrainian', 'United Arab Emirates|Emirati', 'United Kingdom|British', 'United States|American', 'Uruguay|Uruguayan', 'Uzbekistan|Uzbekistani', 'Vanuatu|Ni-Vanuatu', 'Vatican City|Vatican', 'Venezuela|Venezuelan', 'Vietnam|Vietnamese', 'Yemen|Yemeni', 'Zambia|Zambian', 'Zimbabwe|Zimbabwean',
        ];

        foreach ($records as $record) {
            [$country, $nationality] = explode('|', $record, 2);
            $existing = DB::table('tb_country')->where('country_name_en', $country)->first();
            if ($existing) {
                DB::table('tb_country')->where('id', $existing->id)->update([
                    'nationality_name_en' => $nationality,
                    'nationality_name_kh' => $existing->nationality_name_kh ?: null,
                ]);
                continue;
            }

            DB::table('tb_country')->insert([
                'country_name_en' => $country,
                'country_name_kh' => null,
                'nationality_name_en' => $nationality,
                'nationality_name_kh' => null,
                'country_code' => null,
                'flag_path' => null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Master data is intentionally retained if this migration is rolled back.
    }
};
