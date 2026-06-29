<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\ServicePurchase;
use App\Models\SubscriptionExtraCharge;
use App\Models\User;
use App\Models\UserSubscription;

class CentralFinanceBillingService
{
    public function createOrUpdateSubscriptionInvoice(UserSubscription $subscription, string $event = 'subscription_purchase'): Invoice
    {
        $subscription->loadMissing(['user.profile', 'subscription']);
        $user = $subscription->user;

        $payload = $this->baseInvoicePayload(
            user: $user,
            invoiceType: 'subscription',
            totalAmount: (float) ($subscription->total_price ?? $subscription->paid_amount ?? 0),
            paidAmount: (float) ($subscription->paid_amount ?? 0),
            notes: 'فاتورة اشتراك (' . $event . ')'
        );

        $invoice = Invoice::query()->updateOrCreate(
            [
                'source_type' => 'user_subscription',
                'source_id' => (int) $subscription->id,
                'source_event' => $event,
            ],
            $payload
        );

        // Keep one clear accounting line for the subscription purchase/renewal.
        $invoice->items()->delete();
        $invoice->items()->create([
            'source_type' => 'user_subscription',
            'source_id' => $subscription->id,
            'description' => 'اشتراك: ' . ($subscription->subscription?->title_ar ?? 'باقة اشتراك') . ' (' . $event . ')',
            'quantity' => 1,
            'unit_price' => (float) ($subscription->total_price ?? $subscription->paid_amount ?? 0),
            'discount' => 0,
            'total' => (float) ($subscription->total_price ?? $subscription->paid_amount ?? 0),
        ]);

        if ($invoice->sales_owner_id) {
            $salesUser = User::query()->find($invoice->sales_owner_id);
            app(SalesCommissionService::class)->createOrUpdateInvoiceSalesCommission($invoice, $salesUser);
        }

        if ((float) $invoice->paid_amount > 0) {
            app(CentralReceiptService::class)->issueReceiptForInvoice(
                invoice: $invoice,
                amount: (float) $invoice->paid_amount,
                paymentMethod: 'bank_transfer',
                cashBoxId: null,
                meta: [
                    'source_type' => 'user_subscription',
                    'source_id' => (int) $subscription->id,
                    'source_event' => $event,
                    'reference_number' => $subscription->payment_reference,
                    'notes' => 'سند قبض اشتراك: ' . ($invoice->invoice_number ?? '#'.$invoice->id),
                    'affects_invoice_balance' => true,
                ]
            );
        }

        return $invoice;
    }

    public function createSubscriptionExtensionInvoice(UserSubscription $subscription, float $extensionFee, ?int $renewalId = null): Invoice
    {
        $subscription->loadMissing(['user.profile']);
        $user = $subscription->user;
        $event = 'subscription_extension' . ($renewalId ? ':' . $renewalId : '');

        $invoice = Invoice::query()->create(array_merge(
            $this->baseInvoicePayload(
                user: $user,
                invoiceType: 'subscription',
                totalAmount: $extensionFee,
                paidAmount: $extensionFee,
                notes: 'رسوم تمديد اشتراك'
            ),
            [
                'source_type' => 'user_subscription',
                'source_id' => (int) $subscription->id,
                'source_event' => $event,
            ]
        ));

        $invoice->items()->create([
            'source_type' => 'subscription_extension',
            'source_id' => $renewalId,
            'description' => 'رسوم تمديد الاشتراك',
            'quantity' => 1,
            'unit_price' => $extensionFee,
            'discount' => 0,
            'total' => $extensionFee,
        ]);

        if ($invoice->sales_owner_id) {
            $salesUser = User::query()->find($invoice->sales_owner_id);
            app(SalesCommissionService::class)->createOrUpdateInvoiceSalesCommission($invoice, $salesUser);
        }

        if ($extensionFee > 0) {
            app(CentralReceiptService::class)->issueReceiptForInvoice(
                invoice: $invoice,
                amount: (float) $extensionFee,
                paymentMethod: 'bank_transfer',
                cashBoxId: null,
                meta: [
                    'source_type' => 'subscription_extension',
                    'source_id' => (int) ($renewalId ?: $subscription->id),
                    'source_event' => 'extension_fee',
                    'reference_number' => $subscription->payment_reference,
                    'notes' => 'سند قبض رسوم تمديد اشتراك',
                    'affects_invoice_balance' => true,
                ]
            );
        }

        return $invoice;
    }

    public function createOrUpdateExtraChargeInvoice(SubscriptionExtraCharge $charge): Invoice
    {
        $charge->loadMissing(['user.profile', 'order']);
        $user = $charge->user;

        $invoiceType = $charge->payment_method === 'deduct_from_cod' ? 'cod' : 'subscription';

        $paidAmount = in_array($charge->payment_method, ['prepaid', 'cash', 'online'], true)
            ? (float) $charge->amount
            : 0.0;

        $invoice = Invoice::query()->updateOrCreate(
            [
                'source_type' => 'subscription_extra_charge',
                'source_id' => (int) $charge->id,
                'source_event' => 'extra_charge',
            ],
            $this->baseInvoicePayload(
                user: $user,
                invoiceType: $invoiceType,
                totalAmount: (float) $charge->amount,
                paidAmount: $paidAmount,
                notes: 'رسوم إضافية: ' . ($charge->description_ar ?: ($charge->charge_type ?? 'extra'))
            )
        );

        $invoice->items()->delete();
        $invoice->items()->create([
            'order_id' => $charge->order_id,
            'source_type' => 'subscription_extra_charge',
            'source_id' => $charge->id,
            'description' => $charge->description_ar ?: ('رسوم إضافية: ' . ($charge->charge_type ?? 'extra')),
            'quantity' => 1,
            'unit_price' => (float) $charge->amount,
            'discount' => 0,
            'total' => (float) $charge->amount,
        ]);

        if ($invoice->sales_owner_id) {
            $salesUser = User::query()->find($invoice->sales_owner_id);
            app(SalesCommissionService::class)->createOrUpdateInvoiceSalesCommission($invoice, $salesUser);
        }

        if ($paidAmount > 0) {
            app(CentralReceiptService::class)->issueReceiptForInvoice(
                invoice: $invoice,
                amount: (float) $paidAmount,
                paymentMethod: (string) ($charge->payment_method ?: 'bank_transfer'),
                cashBoxId: null,
                meta: [
                    'source_type' => 'subscription_extra_charge',
                    'source_id' => (int) $charge->id,
                    'source_event' => 'extra_charge',
                    'notes' => 'سند قبض رسوم إضافية: ' . ($charge->description_ar ?: ($charge->charge_type ?? 'extra')),
                    'affects_invoice_balance' => true,
                ]
            );
        }

        return $invoice;
    }

    public function createOrUpdateServicePurchaseInvoice(ServicePurchase $purchase): Invoice
    {
        $purchase->loadMissing(['user.profile', 'subscriptionPlan']);
        $user = $purchase->user;

        $invoice = Invoice::query()->updateOrCreate(
            [
                'source_type' => 'service_purchase',
                'source_id' => (int) $purchase->id,
                'source_event' => 'confirmed',
            ],
            $this->baseInvoicePayload(
                user: $user,
                invoiceType: 'subscription',
                totalAmount: (float) $purchase->total_amount,
                paidAmount: $purchase->status === 'confirmed' ? (float) $purchase->total_amount : 0.0,
                notes: 'فاتورة شراء خدمة منصة: ' . ($purchase->purchase_number ?: ('#' . $purchase->id))
            )
        );

        $invoice->items()->delete();
        $invoice->items()->create([
            'source_type' => 'service_purchase',
            'source_id' => $purchase->id,
            'order_id' => $purchase->order_id,
            'description' => $purchase->service_name_ar ?: 'شراء خدمة منصة',
            'quantity' => 1,
            'unit_price' => (float) $purchase->total_amount,
            'discount' => 0,
            'total' => (float) $purchase->total_amount,
        ]);

        return $invoice;
    }

    public function settleExtraChargeInvoice(SubscriptionExtraCharge $charge): void
    {
        $invoice = Invoice::query()
            ->where('source_type', 'subscription_extra_charge')
            ->where('source_id', (int) $charge->id)
            ->latest('id')
            ->first();

        if (!$invoice) {
            return;
        }

        $invoice->update([
            'status' => 'paid',
            'paid_amount' => (float) $invoice->total_amount,
            'remaining_amount' => 0,
            'paid_date' => now()->toDateString(),
        ]);
    }

    private function baseInvoicePayload(User $user, string $invoiceType, float $totalAmount, float $paidAmount, string $notes): array
    {
        $profile = $user->profile;
        $billingSettings = BillingSetting::current();

        $totalAmount = max(0, round($totalAmount, 2));
        $paidAmount = max(0, round($paidAmount, 2));
        $remainingAmount = max(0, round($totalAmount - $paidAmount, 2));

        $status = 'issued';
        if ($remainingAmount <= 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partially_paid';
        }

        return [
            'invoice_type' => $invoiceType,
            'status' => $status,
            'user_id' => $user->id,
            'sales_owner_id' => $profile->sales_rep_id,
            'client_name' => $profile->company_name ?: $user->name,
            'client_phone' => $profile->contact_person_mobile ?: $user->mobile,
            'client_address' => $profile->address_line1,
            'subtotal' => $totalAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays((int) ($profile->payment_cycle_days ?? 30))->toDateString(),
            'paid_date' => $remainingAmount <= 0 ? now()->toDateString() : null,
            'notes' => $notes,
            'bank_name_snapshot' => $billingSettings->bank_name,
            'bank_account_name_snapshot' => $billingSettings->bank_account_name,
            'iban_snapshot' => $billingSettings->iban,
            'account_number_snapshot' => $billingSettings->account_number,
            'swift_code_snapshot' => $billingSettings->swift_code,
            'bank_branch_snapshot' => $billingSettings->bank_branch,
            'payment_instructions_snapshot' => $billingSettings->payment_instructions,
        ];
    }
}
