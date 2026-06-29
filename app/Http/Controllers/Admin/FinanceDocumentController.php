<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\FinanceDocument;
use App\Models\User;
use Illuminate\Http\Request;

class FinanceDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = FinanceDocument::with(['branch', 'relatedUser', 'relatedEmployee', 'creator'])
            ->latest('document_date');

        if ($request->filled('document_type')) {
            $query->where('document_type', (string) $request->document_type);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->filled('clearance_status')) {
            $query->where('clearance_status', (string) $request->clearance_status);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        $documents = $query->paginate(20)->appends($request->query());
        $branches = Branch::query()
            ->orderByRaw('COALESCE(title_ar, title_en) asc')
            ->get(['id', 'title_ar', 'title_en']);

        return view('admin.finance-documents.index', compact('documents', 'branches'));
    }

    public function create(Request $request)
    {
        $type = (string) $request->input('type', 'expense_invoice');

        $branches = Branch::query()
            ->orderByRaw('COALESCE(title_ar, title_en) asc')
            ->get(['id', 'title_ar', 'title_en']);
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $employees = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.finance-documents.create', compact('type', 'branches', 'users', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:expense_invoice,purchase_invoice,payment_voucher'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'related_user_id' => ['nullable', 'exists:users,id'],
            'related_employee_id' => ['nullable', 'exists:branch_employees,id'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,submitted,approved,settled,rejected'],
            'clearance_status' => ['nullable', 'in:none,pending,partial,cleared'],
            'description' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $subtotal = (float) $data['subtotal'];
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('finance-documents', 'public');
        }

        $status = (string) $data['status'];

        $document = FinanceDocument::create([
            'document_type' => (string) $data['document_type'],
            'branch_id' => $data['branch_id'] ?? null,
            'related_user_id' => $data['related_user_id'] ?? null,
            'related_employee_id' => $data['related_employee_id'] ?? null,
            'beneficiary_name' => $data['beneficiary_name'] ?? null,
            'title' => (string) $data['title'],
            'document_date' => (string) $data['document_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => $status,
            'clearance_status' => $data['clearance_status'] ?? 'pending',
            'attachment_path' => $attachmentPath,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'approved_by' => in_array($status, ['approved', 'settled'], true) ? auth()->id() : null,
            'settled_by' => $status === 'settled' ? auth()->id() : null,
            'settled_at' => $status === 'settled' ? now() : null,
        ]);

        return redirect()->route('admin.finance-documents.show', $document)
            ->with('success', 'تم حفظ المستند المالي بنجاح.');
    }

    public function show(FinanceDocument $financeDocument)
    {
        $financeDocument->load(['branch', 'relatedUser', 'relatedEmployee', 'creator', 'approver', 'settler']);

        return view('admin.finance-documents.show', compact('financeDocument'));
    }

    public function print(FinanceDocument $financeDocument)
    {
        $financeDocument->load(['branch', 'relatedUser', 'relatedEmployee', 'creator', 'approver', 'settler']);

        return view('admin.finance-documents.print', compact('financeDocument'));
    }

    public function updateStatus(Request $request, FinanceDocument $financeDocument)
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,submitted,approved,settled,rejected'],
            'clearance_status' => ['nullable', 'in:none,pending,partial,cleared'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $status = (string) $data['status'];

        $financeDocument->update([
            'status' => $status,
            'clearance_status' => $data['clearance_status'] ?? $financeDocument->clearance_status,
            'approved_by' => in_array($status, ['approved', 'settled'], true) ? auth()->id() : $financeDocument->approved_by,
            'settled_by' => $status === 'settled' ? auth()->id() : null,
            'settled_at' => $status === 'settled' ? now() : null,
            'notes' => $data['notes'] ?? $financeDocument->notes,
        ]);

        return back()->with('success', 'تم تحديث حالة المستند.');
    }

    public function downloadAttachment(FinanceDocument $financeDocument)
    {
        abort_if(empty($financeDocument->attachment_path), 404);

        $fullPath = storage_path('app/public/' . $financeDocument->attachment_path);
        abort_if(!file_exists($fullPath), 404);

        return response()->download($fullPath);
    }
}
