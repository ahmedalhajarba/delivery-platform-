<?php

namespace App\Notifications;

use App\Models\ServicePurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServicePurchaseReceiptNotification extends Notification
{
    use Queueable;

    /** @var \App\Models\ServicePurchase */
    protected $purchase;

    public function __construct(ServicePurchase $purchase)
    {
        $this->purchase = $purchase;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type'            => 'service_purchase_receipt',
            'title'           => 'تم تأكيد الدفع وإصدار سند قبض',
            'message'         => 'تم تأكيد عملية الشراء رقم ' . $this->purchase->purchase_number . ' وإصدار سند القبض.',
            'purchase_id'     => $this->purchase->id,
            'purchase_number' => $this->purchase->purchase_number,
            'status'          => $this->purchase->status,
            'total_amount'    => (float)$this->purchase->total_amount,
            'invoice_id'      => $this->purchase->invoice_id,
            'receipt_id'      => $this->purchase->receipt_id,
            'invoice_url'     => route('user.platform-services.invoice', $this->purchase),
            'receipt_url'     => route('user.platform-services.receipt', $this->purchase),
        ];
    }
}
