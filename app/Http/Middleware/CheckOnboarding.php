<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Si business_id es null, intentar recuperar el vínculo antes de bloquear
        if (!$user->business_id) {
            $business = Business::where('onboarding_completed', true)->first();

            if ($business) {
                $user->business_id = $business->id;
                $user->saveQuietly();
            } else {
                return redirect()->route('onboarding');
            }
        }

        // business_id ya está asignado — verificar que el onboarding esté completo
        if (!$user->business?->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
