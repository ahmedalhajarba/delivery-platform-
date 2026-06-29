<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OperationalSetting;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $tickets = SupportTicket::where('user_id', auth()->id())->with('order')->latest()->paginate(15);

        return view('user.supportTickets.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        abort_if(!OperationalSetting::getValue('support_ticket_enabled', true), 403);

        $orders = Order::where('user_id', auth()->id())->latest()->take(100)->get();
        $selectedOrder = $request->filled('order_id') ? $orders->firstWhere('id', (int) $request->order_id) : null;

        return view('user.supportTickets.create', compact('orders', 'selectedOrder'));
    }

    public function store(Request $request)
    {
        abort_if(!OperationalSetting::getValue('support_ticket_enabled', true), 403);

        $data = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'waybill_number' => 'nullable|string|max:100',
            'subject' => 'required|string|max:255',
            'category' => 'required|in:shipment_issue,delay,damage,lost,tracking,additional_service,billing,other',
            'priority' => 'required|in:low,normal,high,urgent',
            'description' => 'required|string|max:5000',
            'additional_service_requested' => 'nullable|boolean',
        ]);

        $order = null;
        if (!empty($data['order_id'])) {
            $order = Order::where('user_id', auth()->id())->findOrFail($data['order_id']);
        } elseif (!empty($data['waybill_number'])) {
            $order = Order::where('user_id', auth()->id())->where('waybill_number', $data['waybill_number'])->first();
        }

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'order_id' => optional($order)->id,
            'waybill_number' => optional($order)->waybill_number ?? ($data['waybill_number'] ?? null),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            'source' => 'customer',
            'additional_service_requested' => $request->boolean('additional_service_requested'),
            'description' => $data['description'],
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'sender_type' => 'customer',
            'message' => $data['description'],
        ]);

        return redirect()->route('user.support-tickets.show', $ticket)->with('message', 'تم رفع التذكرة بنجاح');
    }

    public function show(SupportTicket $supportTicket)
    {
        abort_if($supportTicket->user_id !== auth()->id(), 403);

        $supportTicket->load(['order', 'messages.user']);

        return view('user.supportTickets.show', compact('supportTicket'));
    }

    public function reply(Request $request, SupportTicket $supportTicket)
    {
        abort_if($supportTicket->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => auth()->id(),
            'sender_type' => 'customer',
            'message' => $data['message'],
        ]);

        $supportTicket->update([
            'last_reply_at' => now(),
            'status' => 'open',
        ]);

        return back()->with('message', 'تمت إضافة الرد');
    }
}