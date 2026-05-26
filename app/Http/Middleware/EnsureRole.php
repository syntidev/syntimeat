<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($user) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->header('X-Inertia')) {
                return response()->json(['message' => 'Forbidden'], 409)
                    ->header('X-Inertia-Location', route('login'));
            }
            return redirect()->route('login')
                ->withErrors(['email' => 'No tienes permiso para acceder a esta sección.']);
        }

        return $next($request);
    }
}
