<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliverySpeedSetting;
use Illuminate\Http\Request;

class DeliverySpeedSettingsController extends Controller
{
    public function index()
    {
        $speeds = DeliverySpeedSetting::orderBy('sort_order')->get();
        return view('admin.deliverySpeedSettings.index', compact('speeds'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'speeds'                        => ['required', 'array'],
            'speeds.*.id'                   => ['required', 'integer', 'exists:delivery_speed_settings,id'],
            'speeds.*.label_ar'             => ['required', 'string', 'max:80'],
            'speeds.*.description_ar'       => ['nullable', 'string', 'max:255'],
            'speeds.*.max_hours'            => ['nullable', 'integer', 'min:1'],
            'speeds.*.surcharge'            => ['nullable', 'numeric', 'min:0'],
            'speeds.*.surcharge_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'speeds.*.is_flat_surcharge'    => ['nullable', 'boolean'],
            'speeds.*.enabled'              => ['nullable', 'boolean'],
            'speeds.*.sort_order'           => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($request->input('speeds') as $item) {
            DeliverySpeedSetting::where('id', $item['id'])->update([
                'label_ar'          => $item['label_ar'],
                'description_ar'    => $item['description_ar'] ?? null,
                'max_hours'         => $item['max_hours'] ?? null,
                'surcharge'         => $item['surcharge'] ?? 0,
                'surcharge_percent' => $item['surcharge_percent'] ?? 0,
                'is_flat_surcharge' => isset($item['is_flat_surcharge']) ? (bool)$item['is_flat_surcharge'] : true,
                'enabled'           => isset($item['enabled']) ? (bool)$item['enabled'] : false,
                'sort_order'        => $item['sort_order'] ?? 0,
            ]);
        }

        return redirect()->route('admin.delivery-speed-settings.index')
            ->with('success', 'تم حفظ إعدادات سرعة التوصيل بنجاح.');
    }
}
