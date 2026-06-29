<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreCurrencyManagementRequest;
use App\Http\Requests\UpdateCurrencyManagementRequest;
use App\Http\Resources\Admin\CurrencyManagementResource;
use App\Models\CurrencyManagement;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrencyManagementApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('currency_management_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CurrencyManagementResource(CurrencyManagement::all());
    }

    public function store(StoreCurrencyManagementRequest $request)
    {
        $currencyManagement = CurrencyManagement::create($request->all());

        if ($request->input('logo', false)) {
            $currencyManagement->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        return (new CurrencyManagementResource($currencyManagement))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CurrencyManagement $currencyManagement)
    {
        abort_if(Gate::denies('currency_management_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CurrencyManagementResource($currencyManagement);
    }

    public function update(UpdateCurrencyManagementRequest $request, CurrencyManagement $currencyManagement)
    {
        $currencyManagement->update($request->all());

        if ($request->input('logo', false)) {
            if (!$currencyManagement->logo || $request->input('logo') !== $currencyManagement->logo->file_name) {
                if ($currencyManagement->logo) {
                    $currencyManagement->logo->delete();
                }
                $currencyManagement->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($currencyManagement->logo) {
            $currencyManagement->logo->delete();
        }

        return (new CurrencyManagementResource($currencyManagement))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(CurrencyManagement $currencyManagement)
    {
        abort_if(Gate::denies('currency_management_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $currencyManagement->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
