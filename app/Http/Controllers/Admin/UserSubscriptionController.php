<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyUserSubscriptionRequest;
use App\Http\Requests\StoreUserSubscriptionRequest;
use App\Http\Requests\UpdateUserSubscriptionRequest;
use App\Models\SubscriptionsPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class UserSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('user_subscription_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = UserSubscription::with(['user', 'subscription'])->select(sprintf('%s.*', (new UserSubscription())->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'user_subscription_show';
                $editGate = 'user_subscription_edit';
                $deleteGate = 'user_subscription_delete';
                $crudRoutePart = 'user-subscriptions';

                return view('partials.datatablesActions', compact(
                'viewGate',
                'editGate',
                'deleteGate',
                'crudRoutePart',
                'row'
            ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->addColumn('user_name', function ($row) {
                return $row->user ? $row->user->name : '';
            });

            $table->editColumn('user.email', function ($row) {
                return $row->user ? (is_string($row->user) ? $row->user : $row->user->email) : '';
            });
            $table->addColumn('subscription_title_ar', function ($row) {
                return $row->subscription ? $row->subscription->title_ar : '';
            });

            $table->editColumn('subscription.title_en', function ($row) {
                return $row->subscription ? (is_string($row->subscription) ? $row->subscription : $row->subscription->title_en) : '';
            });
            $table->editColumn('monthly_price', function ($row) {
                return $row->monthly_price ? $row->monthly_price : '';
            });
            $table->editColumn('discount', function ($row) {
                return $row->discount ? $row->discount : '';
            });
            $table->editColumn('order_limit', function ($row) {
                return $row->order_limit ? $row->order_limit : '';
            });

            $table->editColumn('status', function ($row) {
                return $row->status ? UserSubscription::STATUS_SELECT[$row->status] : '';
            });

            $table->rawColumns(['actions', 'placeholder', 'user', 'subscription']);

            return $table->make(true);
        }

        return view('admin.userSubscriptions.index');
    }

    public function create()
    {
        abort_if(Gate::denies('user_subscription_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscriptions = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.userSubscriptions.create', compact('users', 'subscriptions'));
    }

    public function store(StoreUserSubscriptionRequest $request)
    {
        $userSubscription = UserSubscription::create($request->all());

        return redirect()->route('admin.user-subscriptions.index');
    }

    public function edit(UserSubscription $userSubscription)
    {
        abort_if(Gate::denies('user_subscription_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscriptions = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $userSubscription->load('user', 'subscription');

        return view('admin.userSubscriptions.edit', compact('users', 'subscriptions', 'userSubscription'));
    }

    public function update(UpdateUserSubscriptionRequest $request, UserSubscription $userSubscription)
    {
        $userSubscription->update($request->all());

        return redirect()->route('admin.user-subscriptions.index');
    }

    public function show(UserSubscription $userSubscription)
    {
        abort_if(Gate::denies('user_subscription_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userSubscription->load('user', 'subscription');

        return view('admin.userSubscriptions.show', compact('userSubscription'));
    }

    public function destroy(UserSubscription $userSubscription)
    {
        abort_if(Gate::denies('user_subscription_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userSubscription->delete();

        return back();
    }

    public function massDestroy(MassDestroyUserSubscriptionRequest $request)
    {
        UserSubscription::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
