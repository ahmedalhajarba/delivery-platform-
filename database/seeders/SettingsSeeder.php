<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{

    public function run()
    {
        $Settings = [
            [
                'id'=> 1,
                'title_ar'=>'جايك',
                'title_en'=>'jaeek',
                'site_footer'=>'jaeek',
                'email'=>'jaeek@gmail.com',
                'phone'=>123456789,
                'mobile'=>123456789,
                'mobile_b'=>123456789,
                'mobile_c'=>123456789,
                'ios_url'=>'www.jaeek.sa',
                'android_url'=>'www.jaeek.sa',
                'harmony_url'=>'www.jaeek.sa',
                'description_ar'=>'jaeek jaeek jaeek jaeek jaeek',
                'description_en'=>'jaeek jaeek jaeek jaeek jaeek',
                'key_words_ar'=>'jaeek',
                'key_words_en'=>'jaeek',

            ],
        ];

        SiteSetting::insert($Settings);
    }
}
