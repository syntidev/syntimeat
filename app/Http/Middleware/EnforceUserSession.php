<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // ── Cuenta suspendida ─────────────────────────────────────────────────
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->header('X-Inertia')
                ? response()->json(['message' => 'Session expired'], 409)
                      ->header('X-Inertia-Location', route('login'))
                : redirect()->route('login')
                      ->withErrors(['email' => 'Tu cuenta ha sido suspendida. Contacta al administrador.']);
        }

        // ── Sesión única — otro dispositivo inició sesión ─────────────────────
        $sessionToken = $request->session()->get('user_session_token');

        if ($user->session_token && $sessionToken !== $user->session_token) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->header('X-Inertia')
                ? response()->json(['message' => 'Session expired'], 409)
                      ->header('X-Inertia-Location', route('login'))
                : redirect()->route('login')
                      ->withErrors(['email' => 'Tu sesión fue iniciada en otro dispositivo.']);
        }

        // ── Días habilitados (exento: owner y super_admin) ────────────────────
        if (! $user->hasRole(['owner', 'super_admin'])) {
            $days = $user->access_days;
            if (! empty($days)) {
                $todayKey = strtolower(now()->format('D')); // mon, tue, wed…
                if (! in_array($todayKey, $days, true)) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return $request->header('X-Inertia')
                        ? response()->json(['message' => 'Session expired'], 409)
                              ->header('X-Inertia-Location', route('login'))
                        : redirect()->route('login')
                              ->withErrors(['email' => 'Hoy no tienes acceso habilitado al sistema.']);
                }
            }
        }

        // ── Ventana horaria (exento: owner y super_admin) ─────────────────────
        if (! $user->hasRole(['owner', 'super_admin'])) {
            $start = $user->access_start ?? ($user->branch?->access_start);
            $end   = $user->access_end   ?? ($user->branch?->access_end);

            if ($start && $end) {
                $now       = now()->format('H:i:s');
                $startStr  = $start instanceof \Carbon\Carbon ? $start->format('H:i:s') : $start;
                $endStr    = $end   instanceof \Carbon\Carbon ? $end->format('H:i:s')   : $end;

                $outOfWindow = $startStr <= $endStr
                    ? ($now < $startStr || $now > $endStr)
                    : ($now < $startStr && $now > $endStr); // ventana que cruza medianoche

                if ($outOfWindow) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $label = substr($startStr, 0, 5) . ' – ' . substr($endStr, 0, 5);

                    return $request->header('X-Inertia')
                        ? response()->json(['message' => 'Session expired'], 409)
                              ->header('X-Inertia-Location', route('login'))
                        : redirect()->route('login')
                              ->withErrors(['email' => "Tu acceso está restringido al horario {$label}."]);
                }
            }
        }

        return $next($request);
    }
}
