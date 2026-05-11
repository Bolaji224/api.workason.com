<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Afghanistan', 'code' => 'AF'],
            ['name' => 'Albania', 'code' => 'AL'],
            ['name' => 'Algeria', 'code' => 'DZ'],
            ['name' => 'Argentina', 'code' => 'AR'],
            ['name' => 'Australia', 'code' => 'AU'],
            ['name' => 'Austria', 'code' => 'AT'],
            ['name' => 'Belgium', 'code' => 'BE'],
            ['name' => 'Brazil', 'code' => 'BR'],
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Chile', 'code' => 'CL'],
            ['name' => 'China', 'code' => 'CN'],
            ['name' => 'Colombia', 'code' => 'CO'],
            ['name' => 'Denmark', 'code' => 'DK'],
            ['name' => 'Egypt', 'code' => 'EG'],
            ['name' => 'Ethiopia', 'code' => 'ET'],
            ['name' => 'Finland', 'code' => 'FI'],
            ['name' => 'France', 'code' => 'FR'],
            ['name' => 'Germany', 'code' => 'DE'],
            ['name' => 'Ghana', 'code' => 'GH'],
            ['name' => 'Greece', 'code' => 'GR'],
            ['name' => 'India', 'code' => 'IN'],
            ['name' => 'Indonesia', 'code' => 'ID'],
            ['name' => 'Iran', 'code' => 'IR'],
            ['name' => 'Iraq', 'code' => 'IQ'],
            ['name' => 'Ireland', 'code' => 'IE'],
            ['name' => 'Israel', 'code' => 'IL'],
            ['name' => 'Italy', 'code' => 'IT'],
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Jordan', 'code' => 'JO'],
            ['name' => 'Kenya', 'code' => 'KE'],
            ['name' => 'Malaysia', 'code' => 'MY'],
            ['name' => 'Mexico', 'code' => 'MX'],
            ['name' => 'Morocco', 'code' => 'MA'],
            ['name' => 'Netherlands', 'code' => 'NL'],
            ['name' => 'New Zealand', 'code' => 'NZ'],
            ['name' => 'Nigeria', 'code' => 'NG'],
            ['name' => 'Norway', 'code' => 'NO'],
            ['name' => 'Pakistan', 'code' => 'PK'],
            ['name' => 'Philippines', 'code' => 'PH'],
            ['name' => 'Poland', 'code' => 'PL'],
            ['name' => 'Portugal', 'code' => 'PT'],
            ['name' => 'Romania', 'code' => 'RO'],
            ['name' => 'Russia', 'code' => 'RU'],
            ['name' => 'Saudi Arabia', 'code' => 'SA'],
            ['name' => 'South Africa', 'code' => 'ZA'],
            ['name' => 'South Korea', 'code' => 'KR'],
            ['name' => 'Spain', 'code' => 'ES'],
            ['name' => 'Sweden', 'code' => 'SE'],
            ['name' => 'Switzerland', 'code' => 'CH'],
            ['name' => 'Tanzania', 'code' => 'TZ'],
            ['name' => 'Turkey', 'code' => 'TR'],
            ['name' => 'Uganda', 'code' => 'UG'],
            ['name' => 'Ukraine', 'code' => 'UA'],
            ['name' => 'United Arab Emirates', 'code' => 'AE'],
            ['name' => 'United Kingdom', 'code' => 'GB'],
            ['name' => 'United States', 'code' => 'US'],
            ['name' => 'Venezuela', 'code' => 'VE'],
            ['name' => 'Vietnam', 'code' => 'VN'],
            ['name' => 'Zimbabwe', 'code' => 'ZW'],
        ];

        DB::table('countries')->insertOrIgnore($countries);
    }
}