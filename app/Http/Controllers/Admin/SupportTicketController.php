<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\MarketingCampaign;
use App\Models\SalesLead;
use App\Models\UserSubscription;
use App\Models\User;
use App\Services\CallCenter\CallerLookupService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketController extends Controller
{
    private $callerLookup;

    public function __construct(CallerLookupService $callerLookup)
    {
        $this->callerLookup = $callerLookup;
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $customers = User::orderBy('name')->get();
        $orders = Order::with(['sender', 'recipient'])->latest()->take(300)->get();
        $agents = User::orderBy('name')->get();
        $salesUsers = User::whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get();
        $campaigns = MarketingCampaign::orderByDesc('id')->take(200)->get(['id', 'name', 'campaign_code']);
        $salesLeads = SalesLead::orderByDesc('id')->take(300)->get(['id', 'lead_code', 'company_name', 'contact_name']);
        $subscriptions = UserSubscription::with('user')->orderByDesc('id')->take(200)->get(['id', 'user_id']);

        $selectedOrder = null;
        if ($request->filled('order_id')) {
            $selectedOrder = $orders->firstWhere('id', (int) $request->order_id);
        }

        $selectedCustomer = null;
        if ($request->filled('user_id')) {
            $selectedCustomer = $customers->firstWhere('id', (int) $request->user_id);
        }
        if (!$selectedCustomer && $request->filled('phone')) {
            $selectedCustomer = $this->callerLookup->findCustomerByPhone((string) $request->phone);
        }

        $prefill = [
            'user_id' => old('user_id', optional($selectedCustomer)->id),
            'subject' => old('subject', (string) $request->query('subject', '')),
            'category' => old('category', (string) $request->query('category', 'shipment_issue')),
            'requester_department' => old('requester_department', (string) $request->query('requester_department', 'customer_service')),
            'request_context' => old('request_context', (string) $request->query('request_context', 'customer_profile')),
            'priority' => old('priority', (string) $request->query('priority', 'high')),
            'status' => old('status', 'open'),
            'description' => old('description', (string) $request->query('description', '')),
            'phone' => old('phone', (string) $request->query('phone', '')),
            'call_id' => old('call_id', (string) $request->query('call_id', '')),
            'direction' => old('direction', (string) $request->query('direction', 'inbound')),
        ];

        if ($prefill['subject'] === '' && $prefill['phone'] !== '') {
            $prefix = $prefill['direction'] === 'outbound' ? 'مكالمة صادرة' : 'مكالمة واردة';
            $prefill['subject'] = $prefix . ' - ' . $prefill['phone'];
        }

        if ($prefill['description'] === '' && ($prefill['phone'] !== '' || $prefill['call_id'] !== '')) {
            $lines = ['تم إنشاء التذكرة من تكامل مركز الاتصال (ICTCRM).'];
            if ($prefill['phone'] !== '') {
                $lines[] = 'رقم المتصل: ' . $prefill['phone'];
            }
            if ($prefill['call_id'] !== '') {
                $lines[] = 'معرّف المكالمة: ' . $prefill['call_id'];
            }
            if ($prefill['direction'] !== '') {
                $lines[] = 'اتجاه المكالمة: ' . $prefill['direction'];
            }
            $prefill['description'] = implode("\n", $lines);
        }

        $customerProfileUrl = $selectedCustomer ? route('admin.customers.show', $selectedCustomer) : null;

        return view('admin.support-tickets.create', compact('customers', 'orders', 'agents', 'salesUsers', 'campaigns', 'salesLeads', 'subscriptions', 'selectedOrder', 'prefill', 'customerProfileUrl'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'waybill_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'call_id' => 'nullable|string|max:100',
            'direction' => 'nullable|in:inbound,outbound',
            'subject' => 'required|string|max:255',
            'category' => 'required|in:shipment_issue,delay,damage,lost,tracking,additional_service,billing,other',
            'requester_department' => 'nullable|in:sales,marketing,finance,operations,customer_service,other',
            'request_context' => 'nullable|in:subscription,customer_profile,discount_code,deferred_account,campaign,commission,target,other',
            'priority' => 'required|in:low,normal,high,urgent',
            'status' => 'required|in:open,in_progress,pending_customer,resolved,closed,cancelled',
            'assigned_to' => 'nullable|exists:users,id',
            'sales_user_id' => 'nullable|exists:users,id',
            'campaign_id' => 'nullable|exists:marketing_campaigns,id',
            'sales_lead_id' => 'nullable|exists:sales_leads,id',
            'user_subscription_id' => 'nullable|exists:user_subscriptions,id',
            'description' => 'required|string|max:5000',
            'notes' => 'nullable|string|max:2000',
            'additional_service_requested' => 'nullable|boolean',
        ]);

        $order = null;
        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
        } elseif (!empty($data['waybill_number'])) {
            $order = Order::where('waybill_number', $data['waybill_number'])->first();
        }

        $ticket = SupportTicket::create([
            'user_id' => (int) $data['user_id'],
            'order_id' => optional($order)->id,
            'waybill_number' => optional($order)->waybill_number ?? ($data['waybill_number'] ?? null),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'requester_department' => $data['requester_department'] ?? null,
            'request_context' => $data['request_context'] ?? null,
            'priority' => $data['priority'],
            'status' => $data['status'],
            'source' => 'admin',
            'additional_service_requested' => $request->boolean('additional_service_requested'),
            'assigned_to' => $data['assigned_to'] ?? null,
            'sales_user_id' => $data['sales_user_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'sales_lead_id' => $data['sales_lead_id'] ?? null,
            'user_subscription_id' => $data['user_subscription_id'] ?? null,
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'last_reply_at' => now(),
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        $callMeta = [];
        if (!empty($data['call_id'])) {
            $callMeta[] = 'ICTCRM call_id: ' . $data['call_id'];
        }
        if (!empty($data['phone'])) {
            $callMeta[] = 'Caller phone: ' . $data['phone'];
        }
        if (!empty($data['direction'])) {
            $callMeta[] = 'Call direction: ' . $data['direction'];
        }
        if ($callMeta !== []) {
            $ticket->notes = trim((string) ($ticket->notes ?? ''));
            $ticket->notes = trim($ticket->notes . ($ticket->notes !== '' ? ' | ' : '') . implode(' | ', $callMeta));
            $ticket->save();
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'sender_type' => 'agent',
            'is_internal' => false,
            'message' => $data['description'],
        ]);

        // مهمة HR تلقائية لمتابعة التذكرة
        \App\Models\HrTask::create([
            'title' => 'متابعة تذكرة دعم #' . $ticket->id,
            'description' => $ticket->subject,
            'module' => 'support',
            'task_type' => 'ticket_follow_up',
            'priority' => $ticket->priority === 'urgent' ? 'urgent' : 'normal',
            'status' => 'open',
            'assigned_user_id' => $ticket->assigned_to,
            'created_by' => auth()->id(),
            'related_type' => \App\Models\SupportTicket::class,
            'related_id' => $ticket->id,
            'due_at' => now()->addHours(2),
        ]);

        return redirect()->route('admin.support-tickets.show', $ticket)->with('message', 'تم إنشاء التذكرة بنجاح');
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = SupportTicket::with(['customer', 'order', 'assignee'])->latest();

        foreach (['status', 'category', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->$filter);
            }
        }
        if ($request->filled('requester_department')) {
            $query->where('requester_department', $request->requester_department);
        }
        if ($request->filled('request_context')) {
            $query->where('request_context', $request->request_context);
        }

        $tickets = $query->paginate(20)->appends($request->query());

        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $supportTicket)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $supportTicket->load(['customer', 'order', 'assignee', 'messages.user', 'salesUser', 'campaign', 'salesLead', 'subscription']);
        $agents = User::orderBy('name')->get();

        return view('admin.support-tickets.show', compact('supportTicket', 'agents'));
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,pending_customer,resolved,closed,cancelled',
            'priority' => 'required|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'requester_department' => 'nullable|in:sales,marketing,finance,operations,customer_service,other',
            'request_context' => 'nullable|in:subscription,customer_profile,discount_code,deferred_account,campaign,commission,target,other',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (in_array($data['status'], ['resolved', 'closed'], true) && !$supportTicket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $supportTicket->update($data);

        return back()->with('message', 'تم تحديث التذكرة');
    }

    public function reply(Request $request, SupportTicket $supportTicket)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => auth()->id(),
            'sender_type' => 'agent',
            'is_internal' => $request->boolean('is_internal'),
            'message' => $data['message'],
        ]);

        $supportTicket->update([
            'last_reply_at' => now(),
            'status' => $request->boolean('is_internal') ? $supportTicket->status : 'pending_customer',
        ]);

        return back()->with('message', 'تمت إضافة الرد');
    }
}