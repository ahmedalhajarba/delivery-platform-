<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyTaxSettingRequest;
use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxSettingsController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('tax_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $taxSettings = TaxSetting::all();

        return view('admin.taxSettings.index', compact('taxSettings'));
    }

    public function create()
    {
        abort_if(Gate::denies('tax_setting_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.taxSettings.create');
    }

    public function store(StoreTaxSettingRequest $request)
    {
        $taxSetting = TaxSetting::create($request->all());

        return redirect()->route('admin.tax-settings.index');
    }

    public function edit(TaxSetting $taxSetting)
    {
        abort_if(Gate::denies('tax_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.taxSettings.edit', compact('taxSetting'));
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting)
    {
        $taxSetting->update($request->all());

        return redirect()->route('admin.tax-settings.index');
    }

    public function show(TaxSetting $taxSetting)
    {
        abort_if(Gate::denies('tax_setting_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.taxSettings.show', compact('taxSetting'));
    }

    public function destroy(TaxSetting $taxSetting)
    {
        abort_if(Gate::denies('tax_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $taxSetting->delete();

        return back();
    }

    public function massDestroy(MassDestroyTaxSettingRequest $request)
    {
        TaxSetting::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
