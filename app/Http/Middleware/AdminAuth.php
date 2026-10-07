<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check for admin token (new method)
        $token = $request->bearerToken();

        if ($token) {
            $hashedToken = hash('sha256', $token);
            $user = User::where('admin_token', $hashedToken)
                ->where('admin_token_expires_at', '>', now())
                ->first();

            if ($user && ($user->role === 'admin' || $user->role === 'super_admin')) {
                auth()->setUser($user);
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });
                return $next($request);
            }
        }

        // Check for API key (old method - backward compatibility)
        $apiKey = $request->header('X-API-Key') ?? $request->header('x-api-key');
        $expectedApiKey = env('ADMIN_API_KEY', 'secret123');

        if ($apiKey && $apiKey === $expectedApiKey) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'error' => 'Unauthorized',
        ], 401);
    }
}