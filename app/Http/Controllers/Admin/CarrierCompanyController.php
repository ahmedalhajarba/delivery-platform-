<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarrierCompany;
use App\Models\CarrierWaybill;
use App\Models\Order;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CarrierCompanyController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $carriers = CarrierCompany::withCount('waybills')->orderBy('name_ar')->paginate(20);

        return view('admin.carrier-companies.index', compact('carriers'));
    }

    public function create()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.carrier-companies.create');
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name_ar'              => 'required|string|max:255',
            'name_en'              => 'required|string|max:255',
            'code'                 => 'required|string|max:20|unique:carrier_companies,code',
            'api_endpoint'         => 'nullable|url|max:500',
            'api_key'              => 'nullable|string|max:255',
            'api_secret'           => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:100',
            'contact_person'       => 'nullable|string|max:255',
            'contact_phone'        => 'nullable|string|max:20',
            'contact_email'        => 'nullable|email|max:255',
            'is_active'            => 'boolean',
            'has_api_integration'  => 'boolean',
            'notes'                => 'nullable|string|max:1000',
        ]);

        CarrierCompany::create($data);

        return redirect()->route('admin.carrier-companies.index')
            ->with('message', 'تم إضافة شركة الشحن بنجاح');
    }

    public function edit(CarrierCompany $carrierCompany)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.carrier-companies.edit', compact('carrierCompany'));
    }

    public function update(Request $request, CarrierCompany $carrierCompany)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name_ar'              => 'required|string|max:255',
            'name_en'              => 'required|string|max:255',
            'code'                 => 'required|string|max:20|unique:carrier_companies,code,' . $carrierCompany->id,
            'api_endpoint'         => 'nullable|url|max:500',
            'api_key'              => 'nullable|string|max:255',
            'api_secret'           => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:100',
            'contact_person'       => 'nullable|string|max:255',
            'contact_phone'        => 'nullable|string|max:20',
            'contact_email'        => 'nullable|email|max:255',
            'is_active'            => 'boolean',
            'has_api_integration'  => 'boolean',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $carrierCompany->update($data);

        return redirect()->route('admin.carrier-companies.index')
            ->with('message', 'تم تحديث بيانات شركة الشحن');
    }

    public function destroy(CarrierCompany $carrierCompany)
    {
        abort_if(Gate::denies('order_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $carrierCompany->delete();
        return back()->with('message', 'تم حذف شركة الشحن');
    }

    // ─── ربط بوليصة شركة ناقلة بطلب ─────────────────────────────────────────
    public function linkWaybill(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'order_id'               => 'required|exists:orders,id',
            'carrier_company_id'     => 'required|exists:carrier_companies,id',
            'carrier_waybill_number' => 'required|string|max:100',
            'our_reference_number'   => 'nullable|string|max:100',
            'carrier_cost'           => 'nullable|numeric|min:0',
            'declared_cost'          => 'nullable|numeric|min:0',
            'label_url'              => 'nullable|url|max:500',
            'notes'                  => 'nullable|string|max:500',
        ]);

        $data['created_by'] = auth()->id();

        CarrierWaybill::create($data);

        // تحديث الطلب
        Order::where('id', $data['order_id'])->update([
            'load_id'          => $data['carrier_waybill_number'],
            'load_description' => CarrierCompany::find($data['carrier_company_id'])?->name_ar,
        ]);

        return back()->with('message', 'تم ربط بوليصة شركة الشحن الناقلة بنجاح');
    }

    // ─── مطابقة الفواتير ─────────────────────────────────────────────────────
    public function invoiceReconciliation(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = CarrierWaybill::with(['order', 'carrierCompany'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('carrier_id')) {
            $query->where('carrier_company_id', $request->carrier_id);
        }
        if ($request->filled('match_status')) {
            $query->where('invoice_match_status', $request->match_status);
        }

        $waybills = $query->paginate(30)->withQueryString();
        $carriers = CarrierCompany::where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.carrier-companies.reconciliation', compact('waybills', 'carriers'));
    }

    public function updateReconciliation(Request $request, CarrierWaybill $waybill)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'invoiced_cost'           => 'required|numeric|min:0',
            'carrier_invoice_number'  => 'nullable|string|max:100',
            'carrier_invoice_date'    => 'nullable|date',
            'invoice_match_status'    => 'required|in:pending,matched,discrepancy,approved',
            'notes'                   => 'nullable|string|max:500',
        ]);

        $waybill->update($data);

        return back()->with('message', 'تم تحديث حالة مطابقة الفاتورة');
    }
}
