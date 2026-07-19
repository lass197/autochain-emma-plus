<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReadOnlyAuditor
{
    /**
     * Exceptions écrites autorisées pour l'auditeur (double signature).
     *
     * @var list<string>
     */
    protected array $allowedWritePatterns = [
        'api/v1/blockchain/transactions/*/sign-buyer',
        'api/v1/auth/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === UserRole::Auditeur && ! $request->isMethodSafe()) {
            foreach ($this->allowedWritePatterns as $pattern) {
                if ($request->is($pattern)) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'L\'auditeur / acheteur dispose d\'un accès en lecture seule.',
            ], 403);
        }

        return $next($request);
    }
}
