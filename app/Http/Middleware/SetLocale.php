<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $language = null;

        if (request('change_language')) {
            session()->put('language', request('change_language'));
            session()->put('locale', request('change_language'));
            $language = request('change_language');
        } elseif (session('locale')) {
            $language = session('locale');
        } elseif (session('language')) {
            $language = session('language');
        } elseif (config('panel.primary_language')) {
            $language = config('panel.primary_language');
        }

        if (!in_array($language, ['ar', 'en'], true)) {
            $language = 'ar';
        }

        if (isset($language)) {
            session()->put('language', $language);
            session()->put('locale', $language);
            app()->setLocale($language);
        }

        return $next($request);
    }
}
