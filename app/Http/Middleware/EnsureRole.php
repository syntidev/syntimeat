<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->header('X-Inertia')) {
                return response()->json(['message' => 'No autorizado'], 409)
                    ->header('X-Inertia-Location', route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
