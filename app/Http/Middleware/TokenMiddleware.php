<?php

namespace App\Http\Middleware;

use App\Models\Token;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json(['message' => 'Token is missing'], 401);
        }
        $token = str_replace('Bearer ', '', $token);
        // Find the token in the 'tokens' table
        $tokenRecord = Token::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Login Expired'], 401);
        }
        $request->merge(['auth_user_id' => $tokenRecord->user_id]);
        return $next($request);
    }
}
