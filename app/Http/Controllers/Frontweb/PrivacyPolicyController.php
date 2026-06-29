<?php

namespace App\Http\Controllers\Frontweb;

use App\Http\Controllers\Controller;
use App\Models\LegalResponsibilityPage;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    public function indexPrivacyPolicy (){
        $PrivacyPolicy = LegalResponsibilityPage::whereId(1)->first();
        return view('p2.logistics-privacy-policy', compact('PrivacyPolicy'));
    }//end of indexPrivacyPolicy
}
