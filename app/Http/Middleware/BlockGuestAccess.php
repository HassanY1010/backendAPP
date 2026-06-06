<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockGuestAccess
{
    /**
     * Block guest-role users from accessing authenticated routes.
     * Guests are temporary anonymous users and cannot perform mutations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'guest') {
            return response()->json([
                'message' => 'Guest users cannot perform this action. Please register to continue.',
            ], 403);
        }

        return $next($request);
    }
}
