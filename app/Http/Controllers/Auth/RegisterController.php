<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SalesDiscountCode;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\City;
use Illuminate\Http\Request;
use App\Services\SalesCommissionEngineService;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $commissionEngine;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest');
        $this->commissionEngine = app(SalesCommissionEngineService::class);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name'     => ['required', 'string', 'max:255'],
            'mobile'   => ['required', 'unique:users'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sales_ref' => ['nullable', 'string', 'max:100'],
            'sales_discount_code' => ['nullable', 'string', 'max:100'],
        ]);
    }

    public function showRegistrationForm(Request $request)
    {
        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $salesRef = $request->query('sales_ref');
        $salesDiscountCode = $request->query('sales_discount_code');
        return view('auth.logistics-register', compact('cities', 'salesRef', 'salesDiscountCode'));
    }

    /**
     * Create a new user instance after a valid registration.
     * - Assigns the 'User' role (id=2) automatically
     * - Does NOT set verified=1; verification is handled by the User model observer
     *   which sends VerifyUserNotification and sets a verification_token
     *
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $discountCode = null;
        $salesReferrer = null;

        if (!empty($data['sales_discount_code'])) {
            $discountCode = SalesDiscountCode::query()
                ->where('code', strtoupper(trim((string) $data['sales_discount_code'])))
                ->where('is_active', true)
                ->first();

            if ($discountCode && $discountCode->owner_sales_user_id) {
                $salesReferrer = User::query()->find($discountCode->owner_sales_user_id);
            }
        }

        if (!$salesReferrer && !empty($data['sales_ref'])) {
            $salesReferrer = User::query()->where('sales_referral_code', trim((string) $data['sales_ref']))->first();
        }

        $user = User::create([
            'name'     => $data['name'],
            'mobile'   => $data['mobile'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'status'   => 1,
            'sales_rep_id' => optional($salesReferrer)->id,
            'referred_by_sales_user_id' => optional($salesReferrer)->id,
            'registration_sales_discount_code_id' => optional($discountCode)->id,
        ]);

        if ($salesReferrer) {
            $this->commissionEngine->createRegistrationCommission($user, $salesReferrer, $discountCode);
        }

        // The User model's created() observer handles:
        // 1. Generating a verification_token
        // 2. Attaching the default registration role (config panel.registration_default_role)
        // 3. Sending VerifyUserNotification

        return $user;
    }
}

