<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    // Roles que el owner/admin pueden crear
    private const ALLOWED_ROLES = ['admin', 'cashier', 'analyst', 'branch_admin', 'owner'];

    // ─── Vista principal ──────────────────────────────────────────────────────

    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        $branches = Branch::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'access_start', 'access_end']);

        $users = User::where('business_id', $businessId)
            ->whereNotIn('role', ['owner', 'super_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'branch_id', 'is_active', 'access_start', 'access_end', 'access_days', 'created_at'])
            ->map(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'role'         => $u->role,
                'branch_id'    => $u->branch_id,
                'is_active'    => $u->is_active,
                'access_start' => $u->access_start,
                'access_end'   => $u->access_end,
                'access_days'  => $u->access_days,
                'has_sales'    => Sale::where('cashier_id', $u->id)->exists(),
                'created_at'   => $u->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Settings/Team', [
            'branches'     => $branches,
            'users'        => $users,
            'allowedRoles' => self::ALLOWED_ROLES,
        ]);
    }

    // ─── Crear usuario ────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::user()->business_id;

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->whereNotNull('email')],
            'password'     => ['required', 'string', 'min:8'],
            'role'         => ['required', Rule::in(self::ALLOWED_ROLES)],
            'branch_id'    => ['nullable', 'integer', Rule::exists('branches', 'id')->where('business_id', $businessId)],
            'access_start' => ['nullable', 'date_format:H:i'],
            'access_end'   => ['nullable', 'date_format:H:i', 'required_with:access_start'],
            'access_days'  => ['nullable', 'array'],
            'access_days.*'=> ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
        ]);

        $email = $data['email'] ?? (Str::uuid() . '@internal.syntimeat');

        User::create([
            'name'         => $data['name'],
            'email'        => $email,
            'password'     => Hash::make($data['password']),
            'role'         => $data['role'],
            'business_id'  => $businessId,
            'branch_id'    => $data['branch_id'] ?? null,
            'access_start' => $data['access_start'] ?? null,
            'access_end'   => $data['access_end'] ?? null,
            'access_days'  => ! empty($data['access_days']) ? $data['access_days'] : null,
            'is_active'    => true,
        ]);

        return back()->with('success', 'Usuario creado correctamente.');
    }

    // ─── Actualizar usuario ───────────────────────────────────────────────────

    public function update(Request $request, User $user): RedirectResponse
    {
        $businessId = Auth::user()->business_id;
        abort_unless($user->business_id === $businessId, 403);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)->whereNotNull('email')],
            'password'     => ['nullable', 'string', 'min:8'],
            'role'         => ['required', Rule::in(self::ALLOWED_ROLES)],
            'branch_id'    => ['nullable', 'integer', Rule::exists('branches', 'id')->where('business_id', $businessId)],
            'access_start' => ['nullable', 'date_format:H:i'],
            'access_end'   => ['nullable', 'date_format:H:i', 'required_with:access_start'],
            'access_days'  => ['nullable', 'array'],
            'access_days.*'=> ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
        ]);

        $payload = [
            'name'         => $data['name'],
            'email'        => $data['email'] ?? $user->email,
            'role'         => $data['role'],
            'branch_id'    => $data['branch_id'] ?? null,
            'access_start' => $data['access_start'] ?? null,
            'access_end'   => $data['access_end'] ?? null,
            'access_days'  => ! empty($data['access_days']) ? $data['access_days'] : null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            // Fuerza re-login en todos los dispositivos al cambiar contraseña
            $payload['session_token'] = null;
        }

        $user->update($payload);

        return back()->with('success', 'Usuario actualizado.');
    }

    // ─── Suspender / reactivar ────────────────────────────────────────────────

    public function toggleActive(User $user): RedirectResponse
    {
        abort_unless($user->business_id === Auth::user()->business_id, 403);
        abort_if($user->id === Auth::id(), 422, 'No puedes suspender tu propia cuenta.');

        $user->update([
            'is_active'     => ! $user->is_active,
            'session_token' => null, // fuerza logout inmediato
        ]);

        $label = $user->is_active ? 'reactivado' : 'suspendido';

        return back()->with('success', "{$user->name} ha sido {$label}.");
    }

    // ─── Cerrar sesión activa ────────────────────────────────────────────────

    public function killSession(User $user): RedirectResponse
    {
        abort_unless($user->business_id === Auth::user()->business_id, 403);

        $user->updateQuietly(['session_token' => null]);

        return back()->with('success', "Sesión de {$user->name} cerrada.");
    }

    // ─── Eliminar usuario ─────────────────────────────────────────────────────

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->business_id === Auth::user()->business_id, 403);
        abort_if($user->id === Auth::id(), 422, 'No puedes eliminar tu propia cuenta.');

        if (Sale::where('cashier_id', $user->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: el usuario tiene ventas registradas. Suspéndelo en su lugar.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
