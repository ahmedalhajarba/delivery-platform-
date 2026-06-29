<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyInsuranceRequest;
use App\Http\Requests\StoreInsuranceRequest;
use App\Http\Requests\UpdateInsuranceRequest;
use App\Models\Insurance;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InsurancesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('insurance_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Insurance::query()
            ->with(['order.sender', 'order.recipient', 'invoice', 'receipt'])
            ->latest('id');

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        if (request()->filled('q')) {
            $term = trim((string) request('q'));
            $query->where(function ($sub) use ($term) {
                $sub->where('policy_number', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%")
                    ->orWhere('original_receipt_number', 'like', "%{$term}%")
                    ->orWhereHas('receipt', function ($q) use ($term) {
                        $q->where('receipt_number', 'like', "%{$term}%");
                    })
                    ->orWhereHas('order', function ($q) use ($term) {
                        $q->where('reference_number', 'like', "%{$term}%")
                            ->orWhere('id', $term);
                    });
            });
        }

        $insurances = $query->paginate(25)->appends(request()->query());
        $statusOptions = Insurance::STATUS;

        return view('admin.insurances.index', compact('insurances', 'statusOptions'));
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('insurance_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orders = Order::query()
            ->latest('id')
            ->get()
            ->mapWithKeys(function (Order $order) {
                $label = '#' . $order->id;
                if (!empty($order->reference_number)) {
                    $label .= ' - ' . $order->reference_number;
                }
                return [$order->id => $label];
            })
            ->prepend(trans('global.pleaseSelect'), '');

        $invoices = Invoice::query()
            ->latest('id')
            ->take(300)
            ->get()
            ->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => $invoice->invoice_number ?: ('#' . $invoice->id)])
            ->prepend(trans('global.pleaseSelect'), '');

        $receipts = Receipt::query()
            ->with('invoice')
            ->latest('id')
            ->take(500)
            ->get()
            ->mapWithKeys(function (Receipt $receipt) {
                $label = $receipt->receipt_number ?: ('#' . $receipt->id);
                if ($receipt->invoice?->invoice_number) {
                    $label .= ' - ' . $receipt->invoice->invoice_number;
                }

                return [$receipt->id => $label];
            })
            ->prepend(trans('global.pleaseSelect'), '');

        $statusOptions = Insurance::STATUS;

        $defaultOrderId = $request->filled('order_id') ? (int) $request->order_id : null;
        $defaultInvoiceId = $request->filled('invoice_id') ? (int) $request->invoice_id : null;
        $defaultReceiptId = $request->filled('receipt_id') ? (int) $request->receipt_id : null;

        if ($defaultInvoiceId && !$defaultOrderId) {
            $invoiceOrderId = Order::query()
                ->where('invoice_id', $defaultInvoiceId)
                ->latest('id')
                ->value('id');

            if ($invoiceOrderId) {
                $defaultOrderId = (int) $invoiceOrderId;
            }
        }

        if ($defaultInvoiceId && !$defaultReceiptId) {
            $defaultReceiptId = Receipt::query()
                ->where('invoice_id', $defaultInvoiceId)
                ->latest('id')
                ->value('id');
        }

        $defaultTerms = "1) التغطية تسري خلال مدة البوليصة فقط.\n2) التعويض بحد أقصى القيمة المعلنة للشحنة.\n3) لا تشمل التغطية التلف الناتج عن التغليف غير المناسب.\n4) يتم رفع المطالبة خلال 7 أيام من حالة التسليم.";

        return view('admin.insurances.create', compact('orders', 'invoices', 'receipts', 'statusOptions', 'defaultTerms', 'defaultOrderId', 'defaultInvoiceId', 'defaultReceiptId'));
    }

    public function store(StoreInsuranceRequest $request)
    {
        $payload = $request->validated();

        if (empty($payload['invoice_id']) && !empty($payload['order_id'])) {
            $payload['invoice_id'] = Order::query()->whereKey($payload['order_id'])->value('invoice_id');
        }

        if (!empty($payload['receipt_id']) && empty($payload['original_receipt_number'])) {
            $payload['original_receipt_number'] = Receipt::query()->whereKey($payload['receipt_id'])->value('receipt_number');
        }

        if (empty($payload['receipt_id']) && !empty($payload['invoice_id'])) {
            $payload['receipt_id'] = Receipt::query()
                ->where('invoice_id', $payload['invoice_id'])
                ->where('status', 'confirmed')
                ->latest('id')
                ->value('id');

            if (!empty($payload['receipt_id']) && empty($payload['original_receipt_number'])) {
                $payload['original_receipt_number'] = Receipt::query()->whereKey($payload['receipt_id'])->value('receipt_number');
            }
        }

        if (empty($payload['start_date'])) {
            $payload['start_date'] = now()->toDateString();
        }
        if (empty($payload['issued_at'])) {
            $payload['issued_at'] = now()->toDateString();
        }
        if (empty($payload['end_date'])) {
            $payload['end_date'] = now()->addDays(30)->toDateString();
        }

        $insurance = Insurance::create($payload);

        return redirect()->route('admin.insurances.show', $insurance->id)
            ->with('success', 'تم إصدار بوليصة التأمين بنجاح.');
    }

    public function edit(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orders = Order::query()
            ->latest('id')
            ->get()
            ->mapWithKeys(function (Order $order) {
                $label = '#' . $order->id;
                if (!empty($order->reference_number)) {
                    $label .= ' - ' . $order->reference_number;
                }
                return [$order->id => $label];
            })
            ->prepend(trans('global.pleaseSelect'), '');

        $invoices = Invoice::query()
            ->latest('id')
            ->take(300)
            ->get()
            ->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => $invoice->invoice_number ?: ('#' . $invoice->id)])
            ->prepend(trans('global.pleaseSelect'), '');

        $receipts = Receipt::query()
            ->with('invoice')
            ->latest('id')
            ->take(500)
            ->get()
            ->mapWithKeys(function (Receipt $receipt) {
                $label = $receipt->receipt_number ?: ('#' . $receipt->id);
                if ($receipt->invoice?->invoice_number) {
                    $label .= ' - ' . $receipt->invoice->invoice_number;
                }

                return [$receipt->id => $label];
            })
            ->prepend(trans('global.pleaseSelect'), '');

        $statusOptions = Insurance::STATUS;

        $insurance->load('order');

        return view('admin.insurances.edit', compact('orders', 'invoices', 'receipts', 'insurance', 'statusOptions'));
    }

    public function update(UpdateInsuranceRequest $request, Insurance $insurance)
    {
        $payload = $request->validated();

        if (empty($payload['invoice_id']) && !empty($payload['order_id'])) {
            $payload['invoice_id'] = Order::query()->whereKey($payload['order_id'])->value('invoice_id');
        }

        if (!empty($payload['receipt_id']) && empty($payload['original_receipt_number'])) {
            $payload['original_receipt_number'] = Receipt::query()->whereKey($payload['receipt_id'])->value('receipt_number');
        }

        $insurance->update($payload);

        return redirect()->route('admin.insurances.show', $insurance->id)
            ->with('success', 'تم تحديث بيانات البوليصة.');
    }

    public function show(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $insurance->load([
            'order.sender.country',
            'order.sender.governorate',
            'order.sender.city',
            'order.recipient.country',
            'order.recipient.governorate',
            'order.recipient.city',
            'invoice',
            'receipt',
        ]);

        return view('admin.insurances.show', compact('insurance'));
    }

    public function updateStatus(Request $request, Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Insurance::STATUS)),
        ]);

        $insurance->update(['status' => $data['status']]);

        return back()->with('success', 'تم تحديث حالة البوليصة بنجاح.');
    }

    public function printPolicy(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $insurance->load([
            'order.sender.country',
            'order.sender.governorate',
            'order.sender.city',
            'order.recipient.country',
            'order.recipient.governorate',
            'order.recipient.city',
            'order.user',
            'invoice',
            'receipt',
        ]);

        return view('admin.insurances.print-policy', compact('insurance'));
    }

    public function printCodNotice(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $insurance->load([
            'order.sender.country',
            'order.sender.governorate',
            'order.sender.city',
            'order.recipient.country',
            'order.recipient.governorate',
            'order.recipient.city',
            'order.user',
            'invoice',
        ]);

        return view('admin.insurances.print-cod-notice', compact('insurance'));
    }

    public function destroy(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $insurance->delete();

        return back();
    }

    public function massDestroy(MassDestroyInsuranceRequest $request)
    {
        Insurance::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
