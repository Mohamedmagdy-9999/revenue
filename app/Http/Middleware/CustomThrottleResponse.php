<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

class CustomThrottleResponse extends ThrottleRequests
{
    protected function buildResponse($request, $key, $maxAttempts)
    {
        return response()->json([
            'message' => 'لقد تجاوزت الحد المسموح به من المحاولات، برجاء الانتظار قليلاً ثم المحاولة مرة أخرى.'
        ], 429);
    }
}
