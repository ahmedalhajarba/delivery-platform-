<?php

namespace Database\Seeders;

use App\Models\LegalResponsibilityPage;
use App\Models\TermsCondition;
use Illuminate\Database\Seeder;

class LegalResponsibilityPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {


        $policy = [
            [
                'id'=> 1,
                'title_ar'=>'Welcome to Klaz Terms & Conditions',
                'title_en'=>'Welcome to Klaz Terms & Conditions',
                'text_ar'=>'Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions ',
                'text_en'=>'Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions ',

            ],
        ];
   $terms = [
            [
                'id'=> 1,
                'title_ar'=>'Welcome to Klaz Terms & Conditions',
                'title_en'=>'Welcome to Klaz Terms & Conditions',
                'text_ar'=>'Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions ',
                'text_en'=>'Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions Welcome to Klaz Terms & ConditionsWelcome to Klaz Terms & Conditions ',

            ],
        ];



        LegalResponsibilityPage::insert($policy);
        TermsCondition::insert($terms);




    }
}
