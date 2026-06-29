<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountActivationRequest;
use App\Models\ActivationApprovalStep;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;
use App\Services\SalesWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountActivationController extends Controller
{
    protected $salesWorkflowService;

    public function __construct(SalesWorkflowService $salesWorkflowService)
    {
        $this->salesWorkflowService = $salesWorkflowService;
    }

    public function index(Request $request)
    {
        $query = AccountActivationRequest::with(['user', 'assignedTo', 'contract'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')
            );
        }

        $requests  = $query->paginate(20)->withQueryString();
        $statuses  = AccountActivationRequest::STATUS_LABELS;
        $salesTeam = User::whereHas('roles', fn($q) => $q->whereIn('title', ['sales', 'admin']))->get();

        return view('admin.account-activations.index', compact('requests', 'statuses', 'salesTeam'));
    }

    public function create(Request $request)
    {
        $customers = User::where('user_type', 'customer')
            ->whereIn('account_status', ['pending', 'documents_review'])
            ->orderBy('name')->get();
        $salesTeam = User::whereHas('roles', fn($q) => $q->whereIn('title', ['sales', 'admin']))->get();
        $contracts = collect();

        if ($request->filled('user_id')) {
            $contracts = Contract::where('user_id', $request->user_id)
                ->whereIn('status', ['draft', 'pending_approval', 'active'])
                ->get();
        }

        return view('admin.account-activations.create', compact('customers', 'salesTeam', 'contracts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'             => 'required|exists:users,id',
            'contract_id'         => 'nullable|exists:contracts,id',
            'assigned_to'         => 'nullable|exists:users,id',
            'commercial_register' => 'nullable|string',
            'tax_number'          => 'nullable|string',
            'notes'               => 'nullable|string',
            'id_document'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $customer = User::findOrFail($request->user_id);
        $contract = $request->filled('contract_id') ? Contract::find($request->contract_id) : null;

        $idDocPath = null;
        if ($request->hasFile('id_document')) {
            $idDocPath = $request->file('id_document')->store('activations/documents', 'public');
        }

        [$activation, $created] = $this->salesWorkflowService->ensureActivationRequest(
            $customer,
            $contract,
            $request->assigned_to,
            [
                'commercial_register' => $request->commercial_register,
                'tax_number' => $request->tax_number,
                'notes' => $request->notes,
                'id_document' => $idDocPath,
            ]
        );

        if (!$created) {
            return redirect()
                ->route('admin.account-activations.show', $activation)
                ->with('info', 'يوجد طلب تفعيل نشط مسبقًا لهذا الزبون وتم توجيهك إليه.');
        }

        return redirect()->route('admin.account-activations.index')->with('success', 'تم إنشاء طلب التفعيل');
    }

    public function show(AccountActivationRequest $accountActivation)
    {
        $accountActivation->load([
            'user', 'contract.pricingLines', 'assignedTo',
            'approvalSteps.approver', 'documents.uploadedBy',
        ]);
        return view('admin.account-activations.show', compact('accountActivation'));
    }

    public function processStep(Request $request, AccountActivationRequest $accountActivation)
    {
        $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string',
        ]);

        $currentStep = $accountActivation->approvalSteps()
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();

        if (!$currentStep) {
            return back()->with('error', 'لا توجد خطوة معلقة');
        }

        DB::transaction(function () use ($request, $accountActivation, $currentStep) {
            $currentStep->update([
                'status'      => $request->action,
                'approver_id' => auth()->id(),
                'comment'     => $request->comment,
                'decided_at'  => now(),
            ]);

            if ($request->action === 'rejected') {
                $accountActivation->update([
                    'status'           => 'rejected',
                    'rejection_reason' => $request->comment,
                ]);
                $this->salesWorkflowService->syncCustomerAccountState($accountActivation->user, 'rejected');
                // إلغاء الخطوات المتبقية
                $accountActivation->approvalSteps()
                    ->where('status', 'pending')
                    ->update(['status' => 'skipped']);
                return;
            }

            // الخطوة التالية
            $nextStep = $accountActivation->approvalSteps()
                ->where('step_order', '>', $currentStep->step_order)
                ->where('status', 'pending')
                ->first();

            if ($nextStep) {
                // لا يزال هناك موافقات
                $statusMap = [
                    'sales_manager'   => 'sales_approved',
                    'finance_manager' => 'finance_approved',
                    'ops_manager'     => 'ops_approved',
                ];
                $newStatus = $statusMap[$currentStep->step_role] ?? $accountActivation->status;
                $accountActivation->update(['status' => $newStatus]);
                $this->salesWorkflowService->syncCustomerAccountState($accountActivation->user, $newStatus);
            } else {
                // آخر خطوة - تفعيل الحساب
                $accountActivation->update([
                    'status'       => 'activated',
                    'activated_at' => now(),
                ]);
                $activeContractId = null;

                // تفعيل العقد إذا كان مرتبطاً
                if ($accountActivation->contract_id) {
                    $accountActivation->contract->update([
                        'status'      => 'active',
                        'approved_by' => auth()->id(),
                    ]);
                    $activeContractId = $accountActivation->contract_id;
                }

                $this->salesWorkflowService->syncCustomerAccountState(
                    $accountActivation->user,
                    'activated',
                    $activeContractId
                );
            }
        });

        return back()->with('success', 'تم تسجيل قرار الموافقة');
    }

    public function uploadDocument(Request $request, AccountActivationRequest $accountActivation)
    {
        $request->validate([
            'document_type' => 'required|string',
            'file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('file')->store('activations/docs/' . $accountActivation->id, 'public');

        ContractDocument::create([
            'activation_request_id' => $accountActivation->id,
            'document_type'         => $request->document_type,
            'file_path'             => $path,
            'original_name'         => $request->file('file')->getClientOriginalName(),
            'uploaded_by'           => auth()->id(),
        ]);

        return back()->with('success', 'تم رفع المستند');
    }

    public function myApprovals(Request $request)
    {
        // الموافقات المنتظرة للمستخدم الحالي بناء على دوره
        $userRoles = auth()->user()->roles->pluck('title')->toArray();

        $roleToStep = [
            'sales'           => 'sales_manager',
            'sales_manager'   => 'sales_manager',
            'finance'         => 'finance_manager',
            'finance_manager' => 'finance_manager',
            'operations'      => 'ops_manager',
            'ops_manager'     => 'ops_manager',
        ];

        $myStepRoles = collect($userRoles)
            ->map(fn($r) => $roleToStep[$r] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $pendingSteps = ActivationApprovalStep::with(['request.user', 'request.contract'])
            ->whereIn('step_role', $myStepRoles)
            ->where('status', 'pending')
            ->get();

        return view('admin.account-activations.my-approvals', compact('pendingSteps'));
    }
}
