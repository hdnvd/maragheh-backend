<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows only Android app-user tokens that are not blocked. */
class EnsureAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof AppUser) {
            return response()->json(['message' => 'لطفاً وارد حساب کاربری شوید'], 401);
        }
        if ($user->is_blocked) {
            return response()->json(['message' => 'حساب کاربری شما مسدود شده است'], 403);
        }

        return $next($request);
    }
}
