<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\Region;
use App\Models\Village;
use Illuminate\Database\Seeder;

class CountriesCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $Region = [
            [
                'id'=> 1,
                'country_id'=>1,
                'title_ar'=>'منطقة الاختبار 1',
                'title_en'=>'RegionTest1',
                'slug'=>'RegionTest1',

            ],
        ];
        $Governorate = [
            [
                'id'=> 1,
                'title_ar'=>'محافظة اختبار 1',
                'region_id'=>1,
                'title_en'=>'GovernorateTest1',
                'slug'=>'GovernorateTest1',

            ],
        ];
        $Cities = [
            [
                'id'=> 1,
                'governorate_id'=>1,
                'title_ar'=>'مدينة اختبار 1',
                'title_en'=>'CitiesTest1',
                'slug'=>'CitiesTest1',

            ],
        ];

        $Villages = [
            [
                'id'=> 1,
                'governorate_id'=>1,
                'title_ar'=>'حي اختبار 1',
                'title_en'=>'VillagesTest1',
                'type'=>0,
                'slug'=>'VillagesTest1',

            ],
        ];

        $Neighborhood = [
            [
                'id'=> 1,
                'city_id'=>1,
                'title_ar'=>'قرية اختبار 1',
                'title_en'=>'NeighborhoodTest1',
                'slug'=>'VillagesTest1',
                'type'=>0,

            ],
        ];






        Region::insert($Region);
        Governorate::insert($Governorate);
        City::insert($Cities);
        Village::insert($Villages);
        Neighborhood::insert($Neighborhood);
    }
}
