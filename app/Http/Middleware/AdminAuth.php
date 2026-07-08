<?php

namespace App\Http\Middleware;

use App\Models\AdminSession;
use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token requis'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $session = AdminSession::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session invalide ou expirée'], 401);
        }

        return $next($request);
    }
}
