<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Allow admin and moderator
        if ($user->role === 'admin' || $user->role === 'moderator') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized access',
            'your_role' => $user->role,
            'required_role' => 'admin or moderator'
        ], 403);
    }
}
