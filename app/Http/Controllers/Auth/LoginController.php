<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }


    /**
     * After login: block inactive users before redirect and handle custom redirection.
     */
    protected function authenticated(Request $request, $user)
    {
        \Log::info('Authenticated called for user', [
            'id' => $user->id,
            'is_admin' => $user->is_admin,
            'user_type' => $user->user_type,
            'roles' => $user->roles->pluck('slug')->toArray()
        ]);

        if (isset($user->status) && $user->status == 0) {
            auth()->logout();
            return redirect()->route('login')->with('message', trans('global.account_inactive'));
        }

        // توجيه الأدمن
        if ($this->shouldRedirectToAdmin($user)) {
            \Log::info('Redirecting to /admin for user', ['id' => $user->id]);
            return redirect()->intended('/admin');
        }

        // توجيه المستخدم العادي
        \Log::info('Redirecting to /user/portal for user', ['id' => $user->id]);
        return redirect()->intended('/user/portal');
    }

    /**
     * تحديد إذا كان المستخدم يجب أن يوجه للوحة التحكم
     */
    protected function shouldRedirectToAdmin($user)
    {
        // التحقق من is_admin
        if ($user->is_admin) {
            return true;
        }

        // التحقق من user_type
        if (isset($user->user_type) && (int)$user->user_type === 1) {
            return true;
        }

        // التحقق من الدور
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        // التحقق من الصلاحيات الإدارية
        if (method_exists($user, 'hasPermission')) {
            $adminPermissions = ['users.view', 'dashboard.view', 'admin.access'];
            foreach ($adminPermissions as $permission) {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Support login by email, mobile number, or username.
     */
    protected function credentials(Request $request)
    {
        $login = trim((string) $request->get('email'));
        $normalizedLogin = strtr($login, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $onlyDigits = preg_replace('/\D+/', '', $normalizedLogin) ?? '';

        if (strlen($onlyDigits) === 9) {
            $formattedCode = substr($onlyDigits, 0, 3) . '-' . substr($onlyDigits, 3, 3) . '-' . substr($onlyDigits, 6, 3);

            return ['login_code' => $formattedCode, 'password' => $request->get('password')];
        }

        if (is_numeric($onlyDigits)) {
            return ['mobile' => $onlyDigits, 'password' => $request->get('password')];
        }

        if (filter_var($normalizedLogin, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $normalizedLogin, 'password' => $request->get('password')];
        }

        return ['username' => $normalizedLogin, 'password' => $request->get('password')];
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }
}

