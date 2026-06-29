<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CourierBooking;
use App\Models\OperationalSetting;
use App\Models\Order;
use Illuminate\Http\Request;

class CourierBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $bookings = CourierBooking::where('user_id', auth()->id())->with('order')->latest()->paginate(15);

        return view('user.courierBookings.index', compact('bookings'));
    }

    public function create(Request $request)
    {
        abort_if(!OperationalSetting::getValue('courier_booking_enabled', true), 403);

        $orders = Order::where('user_id', auth()->id())->latest()->take(100)->get();
        $addresses = Address::where('user_id', auth()->id())->latest()->get();
        $selectedOrder = $request->filled('order_id') ? $orders->firstWhere('id', (int) $request->order_id) : null;
        $settings = [
            'pickup_window_from' => OperationalSetting::getValue('pickup_window_from', '08:00'),
            'pickup_window_to' => OperationalSetting::getValue('pickup_window_to', '15:00'),
            'delivery_window_from' => OperationalSetting::getValue('delivery_window_from', '16:00'),
            'delivery_window_to' => OperationalSetting::getValue('delivery_window_to', '23:00'),
            'fee_enabled' => OperationalSetting::getValue('courier_booking_fee_enabled', false),
            'fee_amount' => OperationalSetting::getValue('courier_booking_fee_amount', 0),
        ];

        return view('user.courierBookings.create', compact('orders', 'addresses', 'selectedOrder', 'settings'));
    }

    public function store(Request $request)
    {
        abort_if(!OperationalSetting::getValue('courier_booking_enabled', true), 403);

        $data = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'sender_address_id' => 'nullable|exists:addresses,id',
            'recipient_address_id' => 'nullable|exists:addresses,id',
            'requested_date' => 'required|date|after_or_equal:today',
            'requested_time_from' => 'required|date_format:H:i',
            'requested_time_to' => 'required|date_format:H:i|after:requested_time_from',
            'service_type' => 'required|in:pickup,delivery,exchange,custom',
            'trip_direction' => 'required|in:one_way,round_trip',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (!empty($data['order_id'])) {
            Order::where('id', $data['order_id'])->where('user_id', auth()->id())->firstOrFail();
        }
        foreach (['sender_address_id', 'recipient_address_id'] as $field) {
            if (!empty($data[$field])) {
                Address::where('id', $data[$field])->where('user_id', auth()->id())->firstOrFail();
            }
        }

        $baseFee = OperationalSetting::getValue('courier_booking_fee_enabled', false)
            ? (float) OperationalSetting::getValue('courier_booking_fee_amount', 0)
            : 0;
        $feeAmount = $data['trip_direction'] === 'round_trip' ? ($baseFee * 2) : $baseFee;

        $booking = CourierBooking::create([
            'user_id' => auth()->id(),
            'order_id' => $data['order_id'] ?? null,
            'sender_address_id' => $data['sender_address_id'] ?? null,
            'recipient_address_id' => $data['recipient_address_id'] ?? null,
            'requested_date' => $data['requested_date'],
            'requested_time_from' => $data['requested_time_from'],
            'requested_time_to' => $data['requested_time_to'],
            'service_type' => $data['service_type'],
            'trip_direction' => $data['trip_direction'],
            'fee_amount' => $feeAmount,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('user.courier-bookings.index')->with('message', 'تم إرسال طلب حجز المندوب رقم ' . $booking->booking_number);
    }
}