<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next)
    {
        // ❌ لو مش مسجل على guard المواطنين
        if (! Auth::guard('api_customers')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالدخول',
            ], 401);
        }

        // ✅ تمام — مستخدم citizen
        return $next($request);
    }
}