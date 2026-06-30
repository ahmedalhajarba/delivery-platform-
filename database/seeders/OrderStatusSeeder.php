<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
      public function run()
    {
        $orderStatus = [
            [
                'id'                 => 1,
                'name_ar'            =>'تم التقاط الطلب',
                'name_en'            =>'active'
            ],
            [
                'id'                 => 2,
                'name_ar'            =>'في طريق العبور',
                'name_en'            =>'Charging'
            ],
            [
                'id'                 => 3,
                'name_ar'            =>'فشل التقاط الطلب',
                'name_en'            =>'Connection failed',
            ],
            [
                'id'                 => 4,
                'name_ar'            =>'تم التوصيل',
                'name_en'            =>'Delivered'
            ],
            [
                'id'                 => 5,
                'name_ar'            =>'ملغي',
                'name_en'            =>'Deleted'
            ],
        ];
        OrderStatus::insert($orderStatus);
    }
}
