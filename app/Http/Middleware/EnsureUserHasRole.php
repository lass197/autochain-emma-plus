<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Non authentifié ou compte inactif.'], 401);
        }

        if ($user->role === UserRole::SuperAdmin) {
            return $next($request);
        }

        if (! $user->hasRole($roles)) {
            return response()->json(['message' => 'Accès refusé pour ce rôle.'], 403);
        }

        return $next($request);
    }
}
