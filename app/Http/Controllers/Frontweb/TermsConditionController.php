<?php

namespace App\Http\Controllers\Frontweb;

use App\Http\Controllers\Controller;
use App\Models\TermsCondition;
use Illuminate\Http\Request;

class TermsConditionController extends Controller
{
    public function indexTermsCondition (){
        $termsCondition = TermsCondition::whereId(1)->first();
        return view('p2.logistics-terms-conditions', compact('termsCondition'));
    }//end of indexPrivacyPolicy
}
