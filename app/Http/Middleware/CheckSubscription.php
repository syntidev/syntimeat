<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->business && !$user->business->subscription_active) {
            Auth::logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Sistema en mantenimiento. Contacte a su proveedor.']);
        }

        return $next($request);
    }
}
