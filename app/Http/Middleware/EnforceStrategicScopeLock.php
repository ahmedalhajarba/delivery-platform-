<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceStrategicScopeLock
{
    public function handle(Request $request, Closure $next)
    {
        // تم تعطيل هذا الميدلوير بالكامل بعد حذف منظومة التمويل الاستراتيجي
        return $next($request);
    }
}
