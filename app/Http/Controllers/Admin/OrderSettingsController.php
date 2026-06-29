<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyOrderSettingRequest;
use App\Http\Requests\StoreOrderSettingRequest;
use App\Http\Requests\UpdateOrderSettingRequest;
use App\Models\OrderSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderSettingsController extends Controller
{
    public function waybillSettings()
    {
        abort_if(Gate::denies('order_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orderSetting = OrderSetting::query()->first();

        return view('admin.orderSettings.waybill-settings', compact('orderSetting'));
    }

    public function waybillSettingsSave(Request $request)
    {
        abort_if(Gate::denies('order_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'print_copies' => 'nullable|integer|min:1|max:5',
            'allowed_weight' => 'nullable|numeric|min:0',
            'over_weight_cost' => 'nullable|numeric|min:0',
            'insurance_rate' => 'nullable|numeric|min:0',
            'divided_number' => 'nullable|integer|min:1',
            'shipping_rate' => 'nullable|numeric|min:0',
            'sender' => 'nullable|in:1',
            'recipient' => 'nullable|in:1',
            'shipment_type' => 'nullable|in:1',
            'package_content' => 'nullable|in:1',
            'packages_count' => 'nullable|in:1',
            'package_weight' => 'nullable|in:1',
            'actual_weight' => 'nullable|in:1',
            'length' => 'nullable|in:1',
            'width' => 'nullable|in:1',
            'height' => 'nullable|in:1',
            'stated_value' => 'nullable|in:1',
            'reference_number' => 'nullable|in:1',
            'note' => 'nullable|in:1',
        ]);

        foreach ([
            'sender',
            'recipient',
            'shipment_type',
            'package_content',
            'packages_count',
            'package_weight',
            'actual_weight',
            'length',
            'width',
            'height',
            'stated_value',
            'reference_number',
            'note',
        ] as $toggleField) {
            $validated[$toggleField] = $request->boolean($toggleField) ? '1' : '0';
        }

        OrderSetting::query()->updateOrCreate(
            ['id' => 1],
            $validated
        );

        return back()->with('success', 'تم حفظ إعدادات البوليصة بنجاح.');
    }

    public function index()
    {
        abort_if(Gate::denies('order_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

//        $orderSettings = OrderSetting::all();

//        return view('admin.orderSettings.index', compact('orderSettings'));
        $orderSetting = OrderSetting::where('id',1)->first();
        return view('admin.orderSettings.edit', compact('orderSetting'));

    }

    public function create()
    {
        abort_if(Gate::denies('order_setting_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.orderSettings.create');
    }

    public function store(StoreOrderSettingRequest $request)
    {
        $orderSetting = OrderSetting::create($request->all());

        return redirect()->route('admin.order-settings.index');
    }

    public function edit(OrderSetting $orderSetting)
    {
        abort_if(Gate::denies('order_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.orderSettings.edit', compact('orderSetting'));
    }

    public function update(UpdateOrderSettingRequest $request, OrderSetting $orderSetting)
    {
        $orderSetting->update($request->all());

        return redirect()->route('admin.order-settings.index');
    }

    public function show(OrderSetting $orderSetting)
    {
        abort_if(Gate::denies('order_setting_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.orderSettings.show', compact('orderSetting'));
    }

    public function destroy(OrderSetting $orderSetting)
    {
        abort_if(Gate::denies('order_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orderSetting->delete();

        return back();
    }

    public function massDestroy(MassDestroyOrderSettingRequest $request)
    {
        OrderSetting::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
