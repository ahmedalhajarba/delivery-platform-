<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderSettingRequest;
use App\Http\Requests\UpdateOrderSettingRequest;
use App\Http\Resources\Admin\OrderSettingResource;
use App\Models\OrderSetting;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderSettingsApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('order_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new OrderSettingResource(OrderSetting::all());
    }

    public function store(StoreOrderSettingRequest $request)
    {
        $orderSetting = OrderSetting::create($request->all());

        return (new OrderSettingResource($orderSetting))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(OrderSetting $orderSetting)
    {
        abort_if(Gate::denies('order_setting_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new OrderSettingResource($orderSetting);
    }

    public function update(UpdateOrderSettingRequest $request, OrderSetting $orderSetting)
    {
        $orderSetting->update($request->all());

        return (new OrderSettingResource($orderSetting))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(OrderSetting $orderSetting)
    {
        abort_if(Gate::denies('order_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orderSetting->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
