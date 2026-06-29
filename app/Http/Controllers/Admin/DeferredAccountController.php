<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeferredAccountController extends Controller
{
    /**
     * Show deferred account details + edit form.
     */
    public function account(User $customer)
    {
        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);

        $customer->load(['userOrders.invoice' => function ($q) {
            $q->latest()->limit(10);
        }]);

        $invoices = $customer->userOrders()
            ->whereNotNull('invoice_id')
            ->with('invoice')
            ->latest()
            ->limit(10)
            ->get()
            ->pluck('invoice')
            ->filter();

        $stats = [
            'total_invoices'    => $invoices->count(),
            'paid_invoices'     => $invoices->where('status', 'paid')->count(),
            'overdue_invoices'  => $invoices->where('status', 'overdue')->count(),
            'total_billed'      => (float) $invoices->sum('total_amount'),
            'total_paid'        => (float) $invoices->sum('paid_amount'),
            'total_remaining'   => (float) $invoices->sum('remaining_amount'),
        ];

        // Keep existing UI path for compatibility; pass customer/profile as aliases.
        $company = (object) [
            'id' => $customer->id,
            'name_ar' => $profile->company_name ?: $customer->name,
            'contact_phone' => $profile->contact_person_mobile ?: $customer->mobile,
            'street_name' => $profile->address_line1,
            'credit_limit' => $profile->credit_limit,
            'shipment_limit' => $profile->shipment_limit,
            'discount_percent' => $profile->discount_percent,
            'special_shipping_rate' => $profile->special_shipping_rate,
            'payment_cycle_days' => $profile->payment_cycle_days,
            'contract_number' => $profile->activeContract?->contract_number,
            'contract_start_date' => $profile->activeContract?->start_date,
            'contract_end_date' => $profile->activeContract?->end_date,
            'contact_person' => $profile->contact_person,
            'contact_email' => $customer->email,
            'approved_by' => $profile->deferred_approved_by,
            'approved_at' => $profile->deferred_approved_at,
            'rejection_reason' => $profile->suspension_reason,
            'account_status' => $profile->account_status,
            'is_deferred_account' => ($profile->billing_type === 'deferred'),
            'invoices' => $invoices,
            'approvedBy' => $profile->deferredApprovedBy,
        ];

        return view('admin.companies.account', compact('company', 'stats', 'customer', 'profile'));
    }

    /**
     * Update account settings (credit limit, shipment limit, pricing, etc.).
     */
    public function updateAccount(Request $request, User $customer)
    {
        $data = $request->validate([
            'credit_limit'          => 'required|numeric|min:0',
            'shipment_limit'        => 'required|integer|min:0',
            'discount_percent'      => 'required|numeric|min:0|max:100',
            'special_shipping_rate' => 'nullable|numeric|min:0',
            'payment_cycle_days'    => 'required|integer|min:1',
            'contract_number'       => 'nullable|string|max:100',
            'contract_start_date'   => 'nullable|date',
            'contract_end_date'     => 'nullable|date|after_or_equal:contract_start_date',
            'contact_person'        => 'nullable|string|max:255',
            'contact_email'         => 'nullable|email|max:255',
            'contact_phone'         => 'nullable|string|max:20',
        ]);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $profile->fill([
            'credit_limit' => $data['credit_limit'],
            'shipment_limit' => $data['shipment_limit'],
            'discount_percent' => $data['discount_percent'],
            'special_shipping_rate' => $data['special_shipping_rate'] ?? null,
            'payment_cycle_days' => $data['payment_cycle_days'],
            'contact_person' => $data['contact_person'] ?? null,
            'contact_person_mobile' => $data['contact_phone'] ?? null,
            'address_line1' => $customer->profile?->address_line1,
            'billing_type' => 'deferred',
        ]);
        $profile->deferred_approved_by = $profile->deferred_approved_by ?: Auth::id();
        $profile->deferred_approved_at = $profile->deferred_approved_at ?: now();
        $profile->save();

        return redirect()->route('admin.customers.account', $customer)
            ->with('success', 'تم تحديث إعدادات الحساب الآجل بنجاح.');
    }

    /**
     * Activate a deferred account.
     */
    public function activate(Request $request, User $customer)
    {
        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $profile->billing_type = 'deferred';

        if ($profile->account_status === 'active') {
            return back()->with('info', 'الحساب مفعّل بالفعل.');
        }

        $profile->update([
            'account_status' => 'active',
            'deferred_approved_by' => Auth::id(),
            'deferred_approved_at' => now(),
            'suspension_reason' => null,
        ]);

        return back()->with('success', 'تم تفعيل الحساب الآجل بنجاح.');
    }

    /**
     * Suspend a deferred account.
     */
    public function suspend(Request $request, User $customer)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $profile->billing_type = 'deferred';

        $profile->update([
            'account_status'   => 'suspended',
            'suspension_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم تعليق الحساب الآجل.');
    }

    /**
     * Print the contract/agreement for a deferred account.
     */
    public function printContract(User $customer)
    {
        $profile = $customer->profile;
        if (!$profile || $profile->billing_type !== 'deferred') {
            abort(404);
        }

        $company = (object) [
            'name_ar' => $profile->company_name ?: $customer->name,
            'contact_phone' => $profile->contact_person_mobile ?: $customer->mobile,
            'contact_person' => $profile->contact_person,
            'contact_email' => $customer->email,
            'contract_number' => $profile->activeContract?->contract_number,
            'contract_start_date' => $profile->activeContract?->start_date,
            'contract_end_date' => $profile->activeContract?->end_date,
            'credit_limit' => $profile->credit_limit,
            'discount_percent' => $profile->discount_percent,
            'payment_cycle_days' => $profile->payment_cycle_days,
            'approvedBy' => $profile->deferredApprovedBy,
            'approved_at' => $profile->deferred_approved_at,
            'is_deferred_account' => true,
        ];

        return view('admin.companies.contract-print', compact('company'));
    }
}
