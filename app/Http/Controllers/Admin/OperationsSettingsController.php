<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalSetting;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationsSettingsController extends Controller
{
    protected function definitions(): array
    {
        return [
            'pickup_window_from' => ['group' => 'routing', 'type' => 'time', 'default' => '08:00', 'label' => 'بداية نافذة الاستلام'],
            'pickup_window_to' => ['group' => 'routing', 'type' => 'time', 'default' => '15:00', 'label' => 'نهاية نافذة الاستلام'],
            'delivery_window_from' => ['group' => 'routing', 'type' => 'time', 'default' => '16:00', 'label' => 'بداية نافذة التسليم'],
            'delivery_window_to' => ['group' => 'routing', 'type' => 'time', 'default' => '23:00', 'label' => 'نهاية نافذة التسليم'],
            'courier_booking_enabled' => ['group' => 'booking', 'type' => 'boolean', 'default' => '1', 'label' => 'تفعيل حجز المندوب'],
            'courier_booking_fee_enabled' => ['group' => 'booking', 'type' => 'boolean', 'default' => '0', 'label' => 'تفعيل رسوم الحجز'],
            'courier_booking_fee_amount' => ['group' => 'booking', 'type' => 'number', 'default' => '0', 'label' => 'قيمة رسوم الحجز'],
            'support_ticket_enabled' => ['group' => 'support', 'type' => 'boolean', 'default' => '1', 'label' => 'تفعيل نظام التذاكر'],
        ];
    }

    public function edit()
    {
        abort_if(Gate::denies('order_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $definitions = $this->definitions();
        $settings = [];

        foreach ($definitions as $key => $definition) {
            $settings[$key] = OperationalSetting::getValue($key, $definition['default']);
        }

        return view('admin.operations-settings.edit', compact('definitions', 'settings'));
    }

    public function update(Request $request)
    {
        abort_if(Gate::denies('order_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        foreach ($this->definitions() as $key => $definition) {
            $value = $definition['type'] === 'boolean' ? ($request->has($key) ? '1' : '0') : $request->input($key, $definition['default']);
            OperationalSetting::setValue($key, $value, $definition['type'], $definition['group'], $definition['label']);
        }

        return back()->with('message', 'تم تحديث الإعدادات التشغيلية');
    }
}