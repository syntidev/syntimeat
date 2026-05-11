<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response
    {
        // If authenticated user already has a business in progress, restore session
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->business_id && !session('onboarding_business_id')) {
                session(['onboarding_business_id' => $user->business_id]);
            }
        }

        return Inertia::render('Onboarding/Index', [
            'currentStep' => session('onboarding_step', 1),
        ]);
    }

    public function store(Request $request, int $step): RedirectResponse
    {
        return match ($step) {
            1       => $this->step1($request),
            2       => $this->step2($request),
            3       => $this->step3($request),
            4       => $this->step4($request),
            default => redirect()->route('onboarding'),
        };
    }

    // ─── Steps ───────────────────────────────────────────────────────────────

    private function step1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'rif'        => 'nullable|string|max:50',
            'city'       => 'required|string|max:100',
            'state'      => 'required|string|max:100',
            'phone'      => 'nullable|string|max:30',
        ]);

        // Campos opcionales en el form pero NOT NULL en DB — aplicar fallbacks
        $validated['legal_name'] = $validated['legal_name'] ?? $validated['name'];
        $validated['rif']        = $validated['rif'] ?? '';
        $validated['phone']      = $validated['phone'] ?? '';

        $businessId = session('onboarding_business_id');

        if ($businessId && Business::find($businessId)) {
            Business::where('id', $businessId)->update($validated);
        } else {
            $business = Business::create($validated);
            session(['onboarding_business_id' => $business->id]);
        }

        session(['onboarding_step' => 2]);

        return redirect()->route('onboarding');
    }

    private function step2(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_default' => 'required|in:USD,VES,mixed',
            'rate_source'      => 'required|in:bcv,parallel,negotiated,manual',
            'rate_margin'      => 'required|numeric|min:0|max:99.99',
            'weight_unit'      => 'required|string|max:5',
        ]);

        $this->resolveBusiness()->update($validated);

        session(['onboarding_step' => 3]);

        return redirect()->route('onboarding');
    }

    private function step3(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_prefix'     => 'required|string|max:10',
            'ticket_footer'     => 'nullable|string|max:500',
            'payment_methods'   => 'required|array|min:1',
            'payment_methods.*' => 'string|in:cash,mobile_payment,transfer,usd_cash,zelle',
        ]);

        $business = $this->resolveBusiness();

        $business->update([
            'ticket_prefix' => $validated['ticket_prefix'],
            'ticket_footer' => $validated['ticket_footer'] ?? null,
            'settings'      => array_merge($business->settings ?? [], [
                'payment_methods' => $validated['payment_methods'],
            ]),
        ]);

        session(['onboarding_step' => 4]);

        return redirect()->route('onboarding');
    }

    private function step4(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $business = $this->resolveBusiness();

        $user              = new User();
        $user->name        = $validated['name'];
        $user->email       = $validated['email'];
        $user->password    = Hash::make($validated['password']);
        $user->business_id = $business->id;
        $user->save();

        $business->update(['onboarding_completed' => true]);

        session()->forget(['onboarding_step', 'onboarding_business_id']);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveBusiness(): Business
    {
        $id = session('onboarding_business_id');

        if (!$id) {
            session(['onboarding_step' => 1]);
            abort(redirect()->route('onboarding'));
        }

        return Business::findOrFail($id);
    }
}
