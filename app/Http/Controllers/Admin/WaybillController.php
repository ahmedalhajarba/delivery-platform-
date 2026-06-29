<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderSetting;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WaybillController extends Controller
{
    public function __construct()
    {
        $this->middleware('check.permission:order_show')->only(['print', 'pdf']);
    }
    private function prepareData(Order $order): array
    {
        $order->load([
            'sender.country', 'sender.governorate', 'sender.city',
            'recipient.country', 'recipient.governorate', 'recipient.city',
            'originBranch', 'destinationBranch', 'assignedCourier',
        ]);

        if (empty($order->waybill_number)) {
            $order->update([
                'waybill_number' => 'WB-' . strtoupper(Str::random(6)) . '-' . $order->id,
            ]);
        }

        $orderSetting = OrderSetting::first();
        $siteSetting  = \App\Models\SiteSetting::first();
        $siteName     = $siteSetting?->title_ar ?? config('app.name', 'شركة الشحن');
        $sitePhone    = $siteSetting?->phone ?? '';
        $siteWebsite  = config('app.url');
        $siteLogo     = $siteSetting?->logo?->url ?? null;

        // Generate barcode as SVG bars (no package needed)
        $barcodeImage = null;
        $qrCode       = null;

        // Try real barcode if package exists
        if (class_exists(\Milon\Barcode\DNS1D::class)) {
            try {
                $dns1d = app(\Milon\Barcode\DNS1D::class);
                $barcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($order->waybill_number, 'C128');
            } catch (\Exception $e) {}
        }

        // Try QR code
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            try {
                $trackingUrl = route('tracking.show', $order->waybill_number);
                $qrCode = 'data:image/png;base64,' . base64_encode(
                    \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(80)->generate($trackingUrl)
                );
            } catch (\Exception $e) {}
        }

        $order->increment('print_order_count');

        return compact('order', 'orderSetting', 'siteName', 'siteLogo', 'sitePhone', 'siteWebsite', 'barcodeImage', 'qrCode');
    }

    public function print(Order $order)
    {
        $data = $this->prepareData($order);
        return view('admin.orders.waybill', $data);
    }

    public function pdf(Order $order)
    {
        $data = $this->prepareData($order);
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return view('admin.orders.waybill', $data);
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.orders.waybill', $data)
            ->setPaper([0, 0, 419, 595], 'portrait');
        $filename = 'waybill-' . $data['order']->waybill_number . '.pdf';
        $order->update(['bill_url' => 'waybills/' . $filename]);
        return $pdf->download($filename);
    }
}
