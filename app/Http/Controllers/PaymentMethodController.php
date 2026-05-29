<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\PaymentTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $business = Auth::user()->business;
        $branchId = Auth::user()->branch_id ?? (session('current_branch_id') ?? null);

        $methods = PaymentMethod::where('business_id', $business->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $terminals = PaymentTerminal::where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Settings/PaymentMethods', [
            'methods'   => $methods,
            'terminals' => $terminals,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:80'],
            'type'      => ['required', 'in:cash,transfer,mobile,biometric,other'],
            'bank_name' => ['nullable', 'string', 'max:80'],
        ]);

        $business = Auth::user()->business;
        $branchId = Auth::user()->branch_id ?? (session('current_branch_id') ?? null);

        $maxOrder = PaymentMethod::where('business_id', $business->id)->max('sort_order') ?? 0;

        PaymentMethod::create([
            ...$data,
            'business_id' => $business->id,
            'branch_id'   => $branchId,
            'is_active'   => true,
            'sort_order'  => $maxOrder + 1,
        ]);

        return back()->with('success', 'Método de pago creado.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizeMethod($paymentMethod);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:80'],
            'type'      => ['required', 'in:cash,transfer,mobile,biometric,other'],
            'bank_name' => ['nullable', 'string', 'max:80'],
        ]);

        $paymentMethod->update($data);

        return back()->with('success', 'Método de pago actualizado.');
    }

    public function toggle(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizeMethod($paymentMethod);

        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back();
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizeMethod($paymentMethod);

        $paymentMethod->delete();

        return back()->with('success', 'Método de pago eliminado.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $business = Auth::user()->business;

        foreach ($data['order'] as $position => $id) {
            PaymentMethod::where('id', $id)
                ->where('business_id', $business->id)
                ->update(['sort_order' => $position]);
        }

        return back();
    }

    private function authorizeMethod(PaymentMethod $paymentMethod): void
    {
        $businessId = Auth::user()->business->id;

        abort_unless($paymentMethod->business_id === $businessId, 403);
    }
}
