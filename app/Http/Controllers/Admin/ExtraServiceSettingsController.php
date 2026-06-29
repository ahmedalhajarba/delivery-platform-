<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraServiceSetting;
use Illuminate\Http\Request;

class ExtraServiceSettingsController extends Controller
{
    public function edit()
    {
        $settings = ExtraServiceSetting::first();
        if (!$settings) {
            $settings = ExtraServiceSetting::create([
                'overweight_rate'          => 0,
                'packaging_enabled'        => true,
                'packaging_cost'           => 0,
                'storage_enabled'          => true,
                'storage_normal_daily'     => 0,
                'storage_cold_daily'       => 0,
                'storage_free_days'        => 0,
                'return_enabled'           => true,
                'return_cost'              => 0,
                'delivery_attempt_enabled' => true,
                'delivery_free_attempts'   => 1,
                'delivery_attempt_cost'    => 0,
            ]);
        }
        return view('admin.extraServiceSettings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'overweight_rate'           => ['nullable', 'numeric', 'min:0'],
            'packaging_enabled'         => ['nullable', 'boolean'],
            'packaging_cost'            => ['nullable', 'numeric', 'min:0'],
            'storage_enabled'           => ['nullable', 'boolean'],
            'storage_normal_daily'      => ['nullable', 'numeric', 'min:0'],
            'storage_cold_daily'        => ['nullable', 'numeric', 'min:0'],
            'storage_frozen_daily'      => ['nullable', 'numeric', 'min:0'],
            'storage_free_days'         => ['nullable', 'integer', 'min:0'],
            'cold_shipping_surcharge'   => ['nullable', 'numeric', 'min:0'],
            'frozen_shipping_surcharge' => ['nullable', 'numeric', 'min:0'],
            'dry_shipping_surcharge'    => ['nullable', 'numeric', 'min:0'],
            'return_enabled'            => ['nullable', 'boolean'],
            'return_cost'               => ['nullable', 'numeric', 'min:0'],
            'delivery_attempt_enabled'  => ['nullable', 'boolean'],
            'delivery_free_attempts'    => ['nullable', 'integer', 'min:0'],
            'delivery_attempt_cost'     => ['nullable', 'numeric', 'min:0'],
            'insurance_enabled'         => ['nullable', 'boolean'],
            'insurance_rate'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_enabled'               => ['nullable', 'boolean'],
            'vat_rate'                  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_on_shipping'           => ['nullable', 'boolean'],
            'vat_on_insurance'          => ['nullable', 'boolean'],
            'vat_on_extras'             => ['nullable', 'boolean'],
        ]);

        $settings = ExtraServiceSetting::first();
        $settings->update([
            'overweight_rate'           => $request->input('overweight_rate', 0),
            'packaging_enabled'         => $request->boolean('packaging_enabled'),
            'packaging_cost'            => $request->input('packaging_cost', 0),
            'storage_enabled'           => $request->boolean('storage_enabled'),
            'storage_normal_daily'      => $request->input('storage_normal_daily', 0),
            'storage_cold_daily'        => $request->input('storage_cold_daily', 0),
            'storage_frozen_daily'      => $request->input('storage_frozen_daily', 0),
            'storage_free_days'         => $request->input('storage_free_days', 0),
            'cold_shipping_surcharge'   => $request->input('cold_shipping_surcharge', 0),
            'frozen_shipping_surcharge' => $request->input('frozen_shipping_surcharge', 0),
            'dry_shipping_surcharge'    => $request->input('dry_shipping_surcharge', 0),
            'return_enabled'            => $request->boolean('return_enabled'),
            'return_cost'               => $request->input('return_cost', 0),
            'delivery_attempt_enabled'  => $request->boolean('delivery_attempt_enabled'),
            'delivery_free_attempts'    => $request->input('delivery_free_attempts', 1),
            'delivery_attempt_cost'     => $request->input('delivery_attempt_cost', 0),
            'insurance_enabled'         => $request->boolean('insurance_enabled'),
            'insurance_rate'            => $request->input('insurance_rate', 5),
            'vat_enabled'               => $request->boolean('vat_enabled'),
            'vat_rate'                  => $request->input('vat_rate', 15),
            'vat_on_shipping'           => $request->boolean('vat_on_shipping'),
            'vat_on_insurance'          => $request->boolean('vat_on_insurance'),
            'vat_on_extras'             => $request->boolean('vat_on_extras'),
        ]);

        return redirect()->route('admin.extra-service-settings.edit')
            ->with('message', 'تم حفظ إعدادات الخدمات الإضافية بنجاح');
    }
}
