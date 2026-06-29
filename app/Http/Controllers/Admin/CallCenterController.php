<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\CallCenter\CallerLookupService;
use App\Services\CallCenter\IctcrmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CallCenterController extends Controller
{
    private $callerLookup;
    private $ictcrmClient;

    public function __construct(CallerLookupService $callerLookup, IctcrmClient $ictcrmClient)
    {
        $this->callerLookup = $callerLookup;
        $this->ictcrmClient = $ictcrmClient;
    }

    public function screenPop(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $customer = $this->resolveCustomer($request);
        $context = $this->callerLookup->buildScreenPopContext($request, $customer);

        if ($request->boolean('json')) {
            return response()->json(['ok' => true] + $context);
        }

        return redirect()->to($context['ticket_create_url']);
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'call_id' => 'nullable|string|max:100',
            'direction' => 'nullable|in:inbound,outbound',
        ]);

        $request->merge($data);

        $customer = $this->resolveCustomer($request);
        $context = $this->callerLookup->buildScreenPopContext($request, $customer);

        return response()->json(['ok' => true] + $context);
    }

    public function createTicket(Request $request): JsonResponse
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'phone' => 'nullable|string|max:30',
            'call_id' => 'nullable|string|max:100',
            'direction' => 'nullable|in:inbound,outbound',
            'subject' => 'nullable|string|max:255',
            'category' => 'nullable|in:' . implode(',', array_keys(SupportTicket::CATEGORY)),
            'priority' => 'nullable|in:low,normal,high,urgent',
            'description' => 'required|string|max:5000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $customer = null;
        if (!empty($data['user_id'])) {
            $customer = User::find($data['user_id']);
        }
        if (!$customer && !empty($data['phone'])) {
            $customer = $this->callerLookup->findCustomerByPhone($data['phone']);
        }

        if (!$customer) {
            return response()->json([
                'ok' => false,
                'message' => 'لم يتم العثور على عميل مطابق. زود user_id أو رقم صحيح.',
            ], 422);
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $direction = $data['direction'] ?? 'inbound';
            $subject = ($direction === 'outbound' ? 'مكالمة صادرة' : 'مكالمة واردة') . ' - ' . ($data['phone'] ?? $customer->mobile);
        }

        $ticket = SupportTicket::create([
            'user_id' => $customer->id,
            'subject' => $subject,
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'high',
            'status' => 'open',
            'source' => 'admin',
            'requester_department' => 'customer_service',
            'request_context' => 'customer_profile',
            'assigned_to' => $data['assigned_to'] ?? null,
            'description' => $data['description'],
            'notes' => $this->buildCallMetaNote($data),
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'sender_type' => 'agent',
            'is_internal' => false,
            'message' => $data['description'],
        ]);

        return response()->json([
            'ok' => true,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'ticket_url' => route('admin.support-tickets.show', $ticket),
            'customer_profile_url' => route('admin.customers.show', $customer),
        ]);
    }

    public function dial(Request $request): JsonResponse
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'extension' => 'required|string|max:30',
            'phone' => 'required|string|max:30',
        ]);

        $result = $this->ictcrmClient->dial($data['extension'], $data['phone']);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function transfer(Request $request): JsonResponse
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'call_id' => 'required|string|max:100',
            'to_extension' => 'required|string|max:30',
        ]);

        $result = $this->ictcrmClient->transfer($data['call_id'], $data['to_extension']);

        return response()->json($result, $result['status'] ?? 200);
    }

    private function resolveCustomer(Request $request): ?User
    {
        if ($request->filled('user_id')) {
            return User::find((int) $request->input('user_id'));
        }

        $phone = $request->input('phone', $request->input('caller_phone'));

        return $this->callerLookup->findCustomerByPhone($phone);
    }

    private function buildCallMetaNote(array $data): ?string
    {
        $lines = [];

        if (!empty($data['call_id'])) {
            $lines[] = 'ICTCRM call_id: ' . $data['call_id'];
        }
        if (!empty($data['phone'])) {
            $lines[] = 'Caller phone: ' . $data['phone'];
        }
        if (!empty($data['direction'])) {
            $lines[] = 'Call direction: ' . $data['direction'];
        }

        return $lines === [] ? null : implode(' | ', $lines);
    }
}
