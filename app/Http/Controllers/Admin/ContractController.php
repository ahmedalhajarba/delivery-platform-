<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractApproval;
use App\Models\ContractPricingLine;
use App\Models\ContractExtraService;
use App\Models\ContractDocument;
use App\Models\CustomerOrderSetting;
use App\Models\Quotation;
use App\Models\QuotationPricingLine;
use App\Models\User;
use App\Services\SalesCustomerDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    protected $salesCustomerDirectory;

    public function __construct(SalesCustomerDirectoryService $salesCustomerDirectory)
    {
        $this->salesCustomerDirectory = $salesCustomerDirectory;
    }

    public function index(Request $request)
    {
        $query = Contract::with(['user', 'createdBy'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $query->where('contract_number', 'like', '%' . $request->search . '%');
        }

        $contracts = $query->paginate(20)->withQueryString();
        $statuses  = Contract::STATUS_LABELS;
        $customers = $this->salesCustomerDirectory->listForSelection();

        return view('admin.contracts.index', compact('contracts', 'statuses', 'customers'));
    }

    public function create(Request $request)
    {
        $customers    = $this->salesCustomerDirectory->listForSelection();
        $serviceTypes = QuotationPricingLine::SERVICE_TYPES;
        $quotation    = null;

        if ($request->filled('from_quotation')) {
            $quotation = Quotation::with(['pricingLines', 'extraServices', 'user'])->findOrFail($request->from_quotation);
        }

        return view('admin.contracts.create', compact('customers', 'serviceTypes', 'quotation'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'                  => 'required|exists:users,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'nullable|date|after:start_date',
            'deferred_days'            => 'nullable|integer|min:0',
            'credit_limit'             => 'nullable|numeric|min:0',
            'discount_percent'         => 'nullable|numeric|min:0|max:100',
            'terms_and_conditions'     => 'nullable|string',
            'pricing_lines'            => 'nullable|array',
            'extra_services'           => 'nullable|array',
            'signed_document'          => 'nullable|file|mimes:pdf|max:10240',
        ]);

        DB::transaction(function () use ($request) {
            $docPath = null;
            if ($request->hasFile('signed_document')) {
                $docPath = $request->file('signed_document')->store('contracts/documents', 'public');
            }

            $contract = Contract::create([
                'user_id'                  => $request->user_id,
                'quotation_id'             => $request->quotation_id,
                'created_by'               => auth()->id(),
                'status'                   => 'draft',
                'start_date'               => $request->start_date,
                'end_date'                 => $request->end_date,
                'auto_renew'               => $request->boolean('auto_renew'),
                'renewal_notice_days'      => $request->renewal_notice_days ?? 30,
                'deferred_payment_enabled' => $request->boolean('deferred_payment_enabled'),
                'deferred_days'            => $request->deferred_days ?? 0,
                'credit_limit'             => $request->credit_limit ?? 0,
                'discount_percent'         => $request->discount_percent ?? 0,
                'custom_order_settings'    => $request->boolean('custom_order_settings'),
                'max_orders_per_day'       => $request->max_orders_per_day,
                'max_weight_per_order'     => $request->max_weight_per_order,
                'max_packages_per_order'   => $request->max_packages_per_order,
                'allow_cod'                => $request->boolean('allow_cod', true),
                'max_cod_amount'           => $request->max_cod_amount,
                'allow_international'      => $request->boolean('allow_international'),
                'allow_express'            => $request->boolean('allow_express', true),
                'allow_storage'            => $request->boolean('allow_storage'),
                'terms_and_conditions'     => $request->terms_and_conditions,
                'internal_notes'           => $request->internal_notes,
                'signed_document'          => $docPath,
            ]);

            foreach (($request->pricing_lines ?? []) as $line) {
                $contract->pricingLines()->create($line);
            }
            foreach (($request->extra_services ?? []) as $svc) {
                $contract->extraServices()->create($svc);
            }

            // إذا تحوّل من عرض، نحدث حالة العرض
            if ($request->quotation_id) {
                Quotation::where('id', $request->quotation_id)->update(['status' => 'converted']);
            }
        });

        return redirect()->route('admin.contracts.index')->with('success', 'تم إنشاء العقد بنجاح');
    }

    public function show(Contract $contract)
    {
        $contract->load([
            'user', 'createdBy', 'approvedBy', 'quotation',
            'pricingLines', 'extraServices', 'approvals.user',
            'documents.uploadedBy', 'customerSettings', 'activationRequest',
        ]);
        return view('admin.contracts.show', compact('contract'));
    }

    public function edit(Contract $contract)
    {
        if (!in_array($contract->status, ['draft', 'pending_approval'])) {
            return back()->with('error', 'لا يمكن تعديل عقد نشط أو منتهٍ');
        }
        $customers    = $this->salesCustomerDirectory->listForSelection();
        $serviceTypes = QuotationPricingLine::SERVICE_TYPES;
        $contract->load(['pricingLines', 'extraServices']);
        return view('admin.contracts.edit', compact('contract', 'customers', 'serviceTypes'));
    }

    public function update(Request $request, Contract $contract)
    {
        if (!in_array($contract->status, ['draft', 'pending_approval'])) {
            return back()->with('error', 'لا يمكن تعديل هذا العقد');
        }

        $request->validate([
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after:start_date',
            'deferred_days'    => 'nullable|integer|min:0',
            'pricing_lines'    => 'nullable|array',
            'signed_document'  => 'nullable|file|mimes:pdf|max:10240',
        ]);

        DB::transaction(function () use ($request, $contract) {
            $docPath = $contract->signed_document;
            if ($request->hasFile('signed_document')) {
                if ($docPath) {
                    Storage::disk('public')->delete($docPath);
                }
                $docPath = $request->file('signed_document')->store('contracts/documents', 'public');
            }

            $contract->update(array_merge($request->only([
                'user_id', 'start_date', 'end_date', 'deferred_days', 'credit_limit',
                'discount_percent', 'terms_and_conditions', 'internal_notes',
                'max_orders_per_day', 'max_weight_per_order', 'max_packages_per_order',
                'max_cod_amount', 'renewal_notice_days',
            ]), [
                'auto_renew'               => $request->boolean('auto_renew'),
                'deferred_payment_enabled' => $request->boolean('deferred_payment_enabled'),
                'custom_order_settings'    => $request->boolean('custom_order_settings'),
                'allow_cod'                => $request->boolean('allow_cod', true),
                'allow_international'      => $request->boolean('allow_international'),
                'allow_express'            => $request->boolean('allow_express', true),
                'allow_storage'            => $request->boolean('allow_storage'),
                'signed_document'          => $docPath,
            ]));

            $contract->pricingLines()->delete();
            $contract->extraServices()->delete();

            foreach (($request->pricing_lines ?? []) as $line) {
                $contract->pricingLines()->create($line);
            }
            foreach (($request->extra_services ?? []) as $svc) {
                $contract->extraServices()->create($svc);
            }
        });

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'تم تحديث العقد');
    }

    public function submitForApproval(Contract $contract)
    {
        if ($contract->status !== 'draft') {
            return back()->with('error', 'العقد ليس في حالة مسودة');
        }
        $contract->update(['status' => 'pending_approval']);
        ContractApproval::create([
            'contract_id' => $contract->id,
            'user_id'     => auth()->id(),
            'action'      => 'submitted',
            'comment'     => 'تم إرسال العقد للمراجعة والموافقة',
        ]);
        return back()->with('success', 'تم إرسال العقد للموافقة');
    }

    public function approve(Request $request, Contract $contract)
    {
        $request->validate(['comment' => 'nullable|string']);

        DB::transaction(function () use ($request, $contract) {
            $contract->update([
                'status'      => 'active',
                'approved_by' => auth()->id(),
            ]);

            ContractApproval::create([
                'contract_id' => $contract->id,
                'user_id'     => auth()->id(),
                'action'      => 'approved',
                'comment'     => $request->comment,
            ]);

            // تفعيل الإعدادات الخاصة للزبون بناء على العقد
            $settings = CustomerOrderSetting::getOrCreateForUser($contract->user_id);
            $settings->update([
                'contract_id'              => $contract->id,
                'allow_cod'                => $contract->allow_cod,
                'allow_international'      => $contract->allow_international,
                'allow_express'            => $contract->allow_express,
                'allow_storage'            => $contract->allow_storage,
                'deferred_payment_enabled' => $contract->deferred_payment_enabled,
                'deferred_days'            => $contract->deferred_days,
                'credit_limit'             => $contract->credit_limit,
                'discount_percent'         => $contract->discount_percent,
                'use_contract_pricing'     => $contract->pricingLines()->exists(),
                'max_orders_per_day'       => $contract->max_orders_per_day ?? 0,
                'max_weight_per_order'     => $contract->max_weight_per_order ?? 0,
                'max_packages_per_order'   => $contract->max_packages_per_order ?? 0,
                'max_cod_amount'           => $contract->max_cod_amount ?? 0,
            ]);

            // تعيين العقد النشط على حساب الزبون
            $contract->user->update(['active_contract_id' => $contract->id]);
        });

        return back()->with('success', 'تم الموافقة على العقد وتفعيل إعدادات الزبون');
    }

    public function suspend(Request $request, Contract $contract)
    {
        $request->validate(['comment' => 'required|string']);
        $contract->update(['status' => 'suspended']);
        ContractApproval::create([
            'contract_id' => $contract->id,
            'user_id'     => auth()->id(),
            'action'      => 'suspended',
            'comment'     => $request->comment,
        ]);
        return back()->with('success', 'تم تعليق العقد');
    }

    public function terminate(Request $request, Contract $contract)
    {
        $request->validate(['comment' => 'required|string']);
        $contract->update(['status' => 'terminated']);
        ContractApproval::create([
            'contract_id' => $contract->id,
            'user_id'     => auth()->id(),
            'action'      => 'terminated',
            'comment'     => $request->comment,
        ]);
        return back()->with('success', 'تم إنهاء العقد');
    }

    public function reactivate(Request $request, Contract $contract)
    {
        $request->validate(['comment' => 'nullable|string']);
        $contract->update(['status' => 'active']);
        ContractApproval::create([
            'contract_id' => $contract->id,
            'user_id'     => auth()->id(),
            'action'      => 'reactivated',
            'comment'     => $request->comment ?? 'أعيد تفعيل العقد',
        ]);
        return back()->with('success', 'تم إعادة تفعيل العقد');
    }

    public function uploadDocument(Request $request, Contract $contract)
    {
        $request->validate([
            'document_type' => 'required|string',
            'file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('file')->store('contracts/docs/' . $contract->id, 'public');

        ContractDocument::create([
            'contract_id'   => $contract->id,
            'document_type' => $request->document_type,
            'file_path'     => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', 'تم رفع المستند بنجاح');
    }

    public function customerSettings(Contract $contract)
    {
        $settings = CustomerOrderSetting::getOrCreateForUser($contract->user_id);
        $serviceTypes = QuotationPricingLine::SERVICE_TYPES;
        return view('admin.contracts.customer-settings', compact('contract', 'settings', 'serviceTypes'));
    }

    public function saveCustomerSettings(Request $request, Contract $contract)
    {
        $settings = CustomerOrderSetting::getOrCreateForUser($contract->user_id);
        $settings->update(array_merge($request->only([
            'max_orders_per_day', 'max_weight_per_order', 'max_packages_per_order',
            'max_cod_amount', 'billing_cycle', 'deferred_days', 'credit_limit', 'discount_percent',
        ]), [
            'allow_standard'           => $request->boolean('allow_standard', true),
            'allow_cold'               => $request->boolean('allow_cold'),
            'allow_dry'                => $request->boolean('allow_dry'),
            'allow_frozen'             => $request->boolean('allow_frozen'),
            'allow_express'            => $request->boolean('allow_express', true),
            'allow_international'      => $request->boolean('allow_international'),
            'allow_cod'                => $request->boolean('allow_cod', true),
            'allow_storage'            => $request->boolean('allow_storage'),
            'deferred_payment_enabled' => $request->boolean('deferred_payment_enabled'),
            'use_contract_pricing'     => $request->boolean('use_contract_pricing'),
        ]));

        return back()->with('success', 'تم حفظ إعدادات الزبون');
    }
}
