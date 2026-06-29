<?php

namespace App\Services;

use App\Models\CashBox;
use App\Models\Invoice;
use App\Models\Receipt;

class CentralReceiptService
{
    public function issueReceiptForInvoice(
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        ?int $cashBoxId = null,
        array $meta = []
    ): ?Receipt {
        $amount = max(0, round($amount, 2));
        if ($amount <= 0) {
            return null;
        }

        $cashBox = $this->resolveCashBox($cashBoxId);
        if (!$cashBox) {
            return null;
        }

        $sourceType = $meta['source_type'] ?? null;
        $sourceId = $meta['source_id'] ?? null;
        $sourceEvent = $meta['source_event'] ?? null;

        if ($sourceType && $sourceId) {
            $existing = Receipt::query()
                ->where('source_type', $sourceType)
                ->where('source_id', (int) $sourceId)
                ->when($sourceEvent !== null, fn ($q) => $q->where('source_event', $sourceEvent))
                ->where('status', 'confirmed')
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Receipt::query()->create([
            'invoice_id' => $invoice->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_event' => $sourceEvent,
            'cash_box_id' => $cashBox->id,
            'received_by' => $meta['received_by'] ?? null,
            'user_id' => $invoice->user_id,
            'amount' => $amount,
            'payment_method' => $this->normalizePaymentMethod($paymentMethod),
            'receipt_date' => $meta['receipt_date'] ?? now()->toDateString(),
            'reference_number' => $meta['reference_number'] ?? null,
            'bank_name' => $meta['bank_name'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'status' => 'confirmed',
            'affects_invoice_balance' => (bool) ($meta['affects_invoice_balance'] ?? true),
        ]);
    }

    private function resolveCashBox(?int $cashBoxId): ?CashBox
    {
        if ($cashBoxId) {
            return CashBox::query()->where('is_active', true)->find($cashBoxId);
        }

        return CashBox::query()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'main' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        $paymentMethod = strtolower(trim($paymentMethod));

        $map = [
            'bank_transfer' => 'bank_transfer',
            'cash' => 'cash',
            'online' => 'online',
            'credit_card' => 'online',
            'prepaid' => 'bank_transfer',
            'invoice' => 'bank_transfer',
            'cheque' => 'cheque',
            'cod' => 'cod',
            'deduct_from_cod' => 'cod',
        ];

        return $map[$paymentMethod] ?? 'bank_transfer';
    }
}
