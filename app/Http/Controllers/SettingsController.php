<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\PaymentTerminal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Services\DollarRateService;
use Inertia\Response;

class SettingsController extends Controller
{
    // ─── Tasa manual ─────────────────────────────────────────────────

    public function setManualRate(Request $request, DollarRateService $rates): RedirectResponse
    {
        $request->validate([
            'rate' => 'required|numeric|min:1|max:9999999',
        ]);

        $rates->storeManualRate((float) $request->input('rate'));

        return back()->with('success', 'Tasa manual aplicada.');
    }

    // ─── General ─────────────────────────────────────────────────────────────

    public function general(): Response
    {
        $business = Auth::user()->load('business')->business;

        return Inertia::render('Settings/General', [
            'business' => $business,
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $business = Auth::user()->business;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'legal_name'  => ['nullable', 'string', 'max:255'],
            'rif'         => ['nullable', 'string', 'max:20'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string', 'max:500'],
            'city'        => ['nullable', 'string', 'max:100'],
            'state'       => ['nullable', 'string', 'max:100'],
            'logo'        => ['nullable', 'image', 'max:2048'],
            'theme_color' => ['nullable', 'string', 'in:blue,green,red,orange,purple,teal'],
        ]);

        $file = $request->file('logo');
        if ($file && $file->isValid()) {
            Storage::disk('public')->makeDirectory('logos');
            if ($business->logo_path) {
                Storage::disk('public')->delete($business->logo_path);
            }
            $stored = $file->store('logos', 'public');
            if ($stored) {
                $data['logo_path'] = $stored;
            }
        }

        unset($data['logo']);

        $business->update($data);

        return back()->with('success', 'Información del negocio actualizada.');
    }

    // ─── Usuarios ─────────────────────────────────────────────────────────────

    public function users(): Response
    {
        $business = Auth::user()->business;

        $users = User::where('business_id', $business->id)
            ->where('role', '!=', 'super_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return Inertia::render('Settings/Users', [
            'users' => $users,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $business = Auth::user()->business;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role'     => ['required', Rule::in(['admin', 'cashier', 'supervisor'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'role'        => $data['role'],
            'password'    => Hash::make($data['password']),
            'business_id' => $business->id,
        ]);

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeBusinessOwnership($user);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(['admin', 'cashier', 'supervisor'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $this->authorizeBusinessOwnership($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }

    // ─── Cajas ────────────────────────────────────────────────────────────────

    public function cashRegisters(): Response
    {
        $user     = Auth::user();
        $business = $user->business;
        $branchId = $user->branch_id;

        $registers = CashRegister::where('business_id', $business->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->get(['id', 'name', 'branch_id', 'opened_at', 'closed_at']);

        $branches = \App\Models\Branch::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Settings/CashRegisters', [
            'registers' => $registers,
            'branches'  => $branches,
        ]);
    }

    public function storeCashRegister(Request $request): RedirectResponse
    {
        $user     = Auth::user();
        $business = $user->business;

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        CashRegister::create([
            'name'        => $data['name'],
            'business_id' => $business->id,
            'branch_id'   => $data['branch_id'] ?? $user->branch_id,
            'opened_at'   => null,
        ]);

        return back()->with('success', 'Caja registradora creada.');
    }

    public function updateCashRegister(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        $this->authorizeRegister($cashRegister);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $cashRegister->update($data);

        return back()->with('success', 'Caja actualizada.');
    }

    public function destroyCashRegister(CashRegister $cashRegister): RedirectResponse
    {
        $this->authorizeRegister($cashRegister);
        $cashRegister->delete();

        return back()->with('success', 'Caja eliminada.');
    }

    // ─── Terminales POS ──────────────────────────────────────────────────────

    public function terminals(): Response
    {
        $business = Auth::user()->business;

        $terminals = PaymentTerminal::where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Settings/Terminals', [
            'terminals' => $terminals,
        ]);
    }

    public function storeTerminal(Request $request): RedirectResponse
    {
        $business = Auth::user()->business;

        $data = $request->validate([
            'method'            => ['required', 'string', 'max:50'],
            'bank_name'         => ['required', 'string', 'max:100'],
            'serial'            => ['nullable', 'string', 'max:50'],
            'commercial_number' => ['nullable', 'string', 'max:50'],
        ]);

        $max = PaymentTerminal::where('business_id', $business->id)->max('sort_order') ?? 0;

        PaymentTerminal::create([
            ...$data,
            'business_id' => $business->id,
            'is_active'   => true,
            'sort_order'  => $max + 1,
        ]);

        return back()->with('success', 'Terminal creado.');
    }

    public function updateTerminal(Request $request, PaymentTerminal $terminal): RedirectResponse
    {
        abort_unless($terminal->business_id === Auth::user()->business_id, 403);

        $data = $request->validate([
            'method'            => ['required', 'string', 'max:50'],
            'bank_name'         => ['required', 'string', 'max:100'],
            'serial'            => ['nullable', 'string', 'max:50'],
            'commercial_number' => ['nullable', 'string', 'max:50'],
            'is_active'         => ['boolean'],
        ]);

        $terminal->update($data);

        return back()->with('success', 'Terminal actualizado.');
    }

    public function destroyTerminal(PaymentTerminal $terminal): RedirectResponse
    {
        abort_unless($terminal->business_id === Auth::user()->business_id, 403);
        $terminal->delete();

        return back()->with('success', 'Terminal eliminado.');
    }

    // ─── Guards ───────────────────────────────────────────────────────────────

    private function authorizeBusinessOwnership(User $user): void
    {
        abort_unless($user->business_id === Auth::user()->business_id, 403);
    }

    private function authorizeRegister(CashRegister $register): void
    {
        abort_unless($register->business_id === Auth::user()->business_id, 403);
    }

    // ─── Ticket Preferences ──────────────────────────────────────────────────

    public function ticket(): Response
    {
        $business = Auth::user()->business;

        $defaults = [
            'pos_show_kg_visual'  => false,
            'show_kg'             => true,
            'show_kg_unit_price'  => false,
            'kg_decimals'         => 3,
            'show_bs'             => true,
            'show_usd'            => false,
            'usd_format'          => 'usd',
            'show_description'    => true,
            'show_address'        => true,
            'show_phone'          => false,
            'show_client'         => true,
            'footer_text'         => $business->ticket_footer ?? '',
            'ticket_prefix'       => $business->ticket_prefix ?? 'VEN',
        ];

        $ticketPrefs = (array) ($business->settings['ticket'] ?? []);
        $posPrefs    = (array) ($business->settings['pos']    ?? []);

        $prefs = array_merge($defaults, $ticketPrefs, [
            'pos_show_kg_visual' => $posPrefs['show_kg_visual'] ?? false,
        ]);
        $prefs['footer_text']   = $business->ticket_footer ?? '';
        $prefs['ticket_prefix'] = $business->ticket_prefix ?? 'VEN';

        return Inertia::render('Settings/Ticket', [
            'business' => $business,
            'prefs'    => $prefs,
        ]);
    }

    public function updateTicket(Request $request): RedirectResponse
    {
        $business = Auth::user()->business;

        $data = $request->validate([
            'pos_show_kg_visual' => ['boolean'],
            'show_kg'            => ['boolean'],
            'show_kg_unit_price' => ['boolean'],
            'kg_decimals'        => ['required', 'integer', 'in:2,3'],
            'show_bs'            => ['boolean'],
            'show_usd'           => ['boolean'],
            'usd_format'         => ['required', 'string', 'in:usd,ref'],
            'show_description'   => ['boolean'],
            'show_address'       => ['boolean'],
            'show_phone'         => ['boolean'],
            'show_client'        => ['boolean'],
            'footer_text'        => ['nullable', 'string', 'max:200'],
            'ticket_prefix'      => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/'],
        ]);

        $settings        = (array) ($business->settings ?? []);
        $settings['pos'] = [
            'show_kg_visual' => (bool) ($data['pos_show_kg_visual'] ?? false),
        ];
        $settings['ticket'] = [
            'show_kg'            => (bool) ($data['show_kg']            ?? true),
            'show_kg_unit_price' => (bool) ($data['show_kg_unit_price'] ?? false),
            'kg_decimals'        => (int)  ($data['kg_decimals']        ?? 3),
            'show_bs'            => (bool) ($data['show_bs']            ?? true),
            'show_usd'           => (bool) ($data['show_usd']           ?? false),
            'usd_format'         => $data['usd_format'],
            'show_description'   => (bool) ($data['show_description']   ?? true),
            'show_address'       => (bool) ($data['show_address']       ?? true),
            'show_phone'         => (bool) ($data['show_phone']         ?? false),
            'show_client'        => (bool) ($data['show_client']        ?? true),
        ];

        $business->update([
            'settings'      => $settings,
            'ticket_footer' => $data['footer_text'] ?? null,
            'ticket_prefix' => $data['ticket_prefix'],
        ]);

        return back()->with('success', 'Preferencias del ticket actualizadas.');
    }

    // ─── Sucursales ──────────────────────────────────────────────────────────

    public function branches(): Response
    {
        $businessId = Auth::user()->business_id;

        $branches = Branch::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'city', 'phone', 'is_active']);

        return Inertia::render('Settings/Branches', [
            'branches' => $branches,
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'city'    => ['nullable', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
        ]);

        Branch::create([
            'business_id' => Auth::user()->business_id,
            'is_active'   => true,
            ...$data,
        ]);

        return back()->with('success', 'Sucursal creada.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->business_id === Auth::user()->business_id, 403);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'address'   => ['nullable', 'string', 'max:200'],
            'city'      => ['nullable', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($data);

        return back()->with('success', 'Sucursal actualizada.');
    }
}
