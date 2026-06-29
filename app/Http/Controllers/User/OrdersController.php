<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasPermissionTo('order_access')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
                       ->with('order_status')
                       ->latest()
                       ->paginate(15);

        return view('user.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $order->load('order_status');
        return view('user.orders.show', compact('order'));
    }

    public function create()
    {
        abort_if(!auth()->user()->hasPermissionTo('order_create'), 403);
        return view('user.orders.create');
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->hasPermissionTo('order_create'), 403);

        $validated = $request->validate([
            'package_type'    => 'required|string|max:255',
            'package_content' => 'nullable|string|max:500',
            'packages_count'  => 'nullable|integer|min:1',
            'package_weight'  => 'nullable|numeric|min:0',
            'note'            => 'nullable|string|max:1000',
            'order_type'      => 'nullable|in:subscription,deferred,single',
        ]);

        if (($validated['order_type'] ?? null) === 'subscription') {
            $subscriptionService = app(\App\Services\SubscriptionService::class);
            $eligibility = $subscriptionService->canCreateSubscriptionOrder((int) auth()->id());
            if (empty($eligibility['allowed'])) {
                return back()->withErrors(['order_type' => (string) $eligibility['message']])->withInput();
            }
        }

        $validated['user_id'] = auth()->id();

        $order = Order::create($validated);

        return redirect()->route('user.orders.show', $order)->with('message', trans('global.order_created'));
    }
}
