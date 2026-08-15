<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enum\Role;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        foreach ($roles as $role) {
            try {
                if ($user->role === Role::from($role)) {
                    return $next($request);
                }
            } catch (\ValueError) {
                continue;
            }
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
        ], 403);
    }
}