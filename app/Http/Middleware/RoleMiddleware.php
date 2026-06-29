<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // التحقق من أن المستخدم مسجل الدخول
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // التحقق من أن المستخدم لديه أي من الأدوار المطلوبة
        if (!auth()->user()->hasAnyRole($roles)) {
            abort(403, 'ليس لديك الصلاحية للوصول إلى هذه الصفحة');
        }

        return $next($request);
    }
}