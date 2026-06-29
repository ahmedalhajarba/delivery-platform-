<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountActivationRequest;
use App\Models\Contract;
use App\Models\CustomerSalesFollowup;
use App\Models\MarketingCampaign;
use App\Models\Quotation;
use App\Models\SalesLead;
use App\Models\SupportTicket;
use App\Services\SalesWorkflowService;
use Illuminate\Http\Request;

class SalesOperationsController extends Controller
{
    protected $salesWorkflowService;

    public function __construct(SalesWorkflowService $salesWorkflowService)
    {
        $this->salesWorkflowService = $salesWorkflowService;
    }

    public function index(Request $request)
    {
        $this->authorizeSalesAccess();

        $stats = [
            'quotations_draft' => Quotation::query()->whereIn('status', ['draft', 'sent', 'negotiating'])->count(),
            'quotations_approved' => Quotation::query()->where('status', 'approved')->count(),
            'contracts_pending' => Contract::query()->where('status', 'pending_approval')->count(),
            'contracts_active' => Contract::query()->where('status', 'active')->count(),
            'activations_pending' => AccountActivationRequest::query()->whereNotIn('status', ['activated', 'rejected'])->count(),
            'leads_open' => SalesLead::query()->whereNotIn('qualification_status', ['won', 'lost'])->count(),
            'campaigns_active' => MarketingCampaign::query()->whereIn('status', ['planning', 'scheduled', 'active'])->count(),
            'sales_support_tickets_open' => SupportTicket::query()->whereIn('requester_department', ['sales', 'marketing'])->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            'manager_followup_tasks' => CustomerSalesFollowup::query()->where('status', 'planned')->count(),
        ];

        $approvedQuotations = Quotation::query()
            ->with(['user', 'createdBy'])
            ->where('status', 'approved')
            ->whereDoesntHave('contract')
            ->latest('id')
            ->limit(10)
            ->get();

        $contractsReadyForActivation = Contract::query()
            ->with(['user', 'quotation'])
            ->whereIn('status', ['pending_approval', 'active'])
            ->whereDoesntHave('activationRequest', function ($q) {
                $q->whereNotIn('status', ['rejected']);
            })
            ->latest('id')
            ->limit(10)
            ->get();

        $pendingActivations = AccountActivationRequest::query()
            ->with(['user', 'contract', 'assignedTo'])
            ->whereNotIn('status', ['activated', 'rejected'])
            ->latest('id')
            ->limit(10)
            ->get();

        $recentLeads = SalesLead::query()
            ->with(['assignedTo'])
            ->whereNotIn('qualification_status', ['won', 'lost'])
            ->latest('id')
            ->limit(10)
            ->get();

        $recentCampaigns = MarketingCampaign::query()
            ->with('assignedTo')
            ->latest('id')
            ->limit(10)
            ->get();

        $salesSupportTickets = SupportTicket::query()
            ->with(['customer', 'assignee', 'salesUser'])
            ->whereIn('requester_department', ['sales', 'marketing'])
            ->latest('id')
            ->limit(10)
            ->get();

        $followupTasks = CustomerSalesFollowup::query()
            ->with(['customer', 'salesUser', 'branch', 'city'])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.sales-operations.index', compact(
            'stats',
            'approvedQuotations',
            'contractsReadyForActivation',
            'pendingActivations',
            'recentLeads',
            'recentCampaigns',
            'salesSupportTickets',
            'followupTasks'
        ));
    }

    public function createActivationFromContract(Contract $contract)
    {
        $this->authorizeSalesAccess();

        [$activation, $created] = $this->salesWorkflowService->ensureActivationRequest(
            $contract->user,
            $contract,
            auth()->id(),
            [
                'commercial_register' => optional($contract->user->profile)->commercial_register,
                'tax_number' => optional($contract->user->profile)->tax_number,
                'notes' => 'طلب تفعيل تم إنشاؤه من مركز المبيعات الموحد.',
            ]
        );

        return redirect()
            ->route('admin.account-activations.show', $activation)
            ->with(
                'success',
                $created
                    ? 'تم إنشاء طلب تفعيل الحساب من العقد بنجاح.'
                    : 'يوجد طلب تفعيل نشط مسبقًا وتم توجيهك إليه.'
            );
    }

    private function authorizeSalesAccess(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->is_admin) {
            return;
        }

        $allowed = ['sales', 'sales_manager'];
        $hasSalesRole = $user->roles()
            ->whereIn('title', $allowed)
            ->exists();

        abort_unless($hasSalesRole, 403, 'Unauthorized sales workspace access.');
    }
}
