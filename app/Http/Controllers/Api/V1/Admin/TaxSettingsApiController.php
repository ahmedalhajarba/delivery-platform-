<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Http\Resources\Admin\TaxSettingResource;
use App\Models\TaxSetting;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxSettingsApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('tax_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new TaxSettingResource(TaxSetting::all());
    }

    public function store(StoreTaxSettingRequest $request)
    {
        $taxSetting = TaxSetting::create($request->all());

        return (new TaxSettingResource($taxSetting))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TaxSetting $taxSetting)
    {
        abort_if(Gate::denies('tax_setting_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new TaxSettingResource($taxSetting);
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting)
    {
        $taxSetting->update($request->all());

        return (new TaxSettingResource($taxSetting))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(TaxSetting $taxSetting)
    {
        abort_if(Gate::denies('tax_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $taxSetting->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
