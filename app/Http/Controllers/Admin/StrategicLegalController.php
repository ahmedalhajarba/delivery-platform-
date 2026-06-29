<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrategicFinance\Counterparty;
use App\Models\StrategicFinance\LegalCase;
use App\Models\StrategicFinance\LegalCaseAssignment;
use App\Models\StrategicFinance\LegalCaseDocument;
use App\Models\StrategicFinance\LegalCaseUpdate;
use App\Models\StrategicFinance\LegalLawyer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StrategicLegalController extends Controller
{
    public function index()
    {
        $caseStats = [
            'total' => (int) LegalCase::count(),
            'open' => (int) LegalCase::where('status', 'open')->count(),
            'in_progress' => (int) LegalCase::where('status', 'in_progress')->count(),
            'closed' => (int) LegalCase::where('status', 'closed')->count(),
        ];

        $lawyers = LegalLawyer::query()
            ->orderBy('full_name')
            ->get();

        $counterparties = Counterparty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cases = LegalCase::query()
            ->with([
                'assignments' => function ($query) {
                    $query->with('lawyer:id,full_name')->latest('assigned_at');
                },
                'updates' => function ($query) {
                    $query->with('lawyer:id,full_name')->latest('update_date')->latest('id');
                },
                'documents' => function ($query) {
                    $query->latest('id');
                },
            ])
            ->latest('id')
            ->limit(25)
            ->get();

        return view('admin.strategic-finance.legal', [
            'caseStats' => $caseStats,
            'lawyers' => $lawyers,
            'counterparties' => $counterparties,
            'cases' => $cases,
        ]);
    }

    public function storeLawyer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['status'] = 'active';

        LegalLawyer::create($validated);

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تمت إضافة المحامي بنجاح.');
    }

    public function storeCase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'title' => ['required', 'string', 'max:255'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'court_name' => ['nullable', 'string', 'max:255'],
            'case_type' => ['required', 'string', 'max:50'],
            'priority_level' => ['required', 'integer', 'min:1', 'max:5'],
            'claim_amount' => ['nullable', 'numeric', 'min:0'],
            'hearing_date' => ['nullable', 'date'],
            'opened_at' => ['nullable', 'date'],
            'target_close_date' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'summary' => ['nullable', 'string'],
        ]);

        $validated['status'] = 'open';
        $validated['created_by'] = optional(auth()->user())->id;

        if (empty($validated['counterparty_name']) && !empty($validated['counterparty_id'])) {
            $counterparty = Counterparty::query()->find($validated['counterparty_id']);
            $validated['counterparty_name'] = $counterparty?->name;
        }

        LegalCase::create($validated);

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تم تسجيل القضية بنجاح.');
    }

    public function assignLawyer(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $validated = $request->validate([
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'assigned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        LegalCaseAssignment::query()
            ->where('legal_case_id', $legalCase->id)
            ->where('status', 'active')
            ->update(['status' => 'reassigned']);

        LegalCaseAssignment::create([
            'legal_case_id' => $legalCase->id,
            'lawyer_id' => (int) $validated['lawyer_id'],
            'assigned_at' => $validated['assigned_at'],
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'created_by' => optional(auth()->user())->id,
        ]);

        if ($legalCase->status === 'open') {
            $legalCase->update(['status' => 'in_progress']);
        }

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تم تكليف المحامي بالقضية.');
    }

    public function addUpdate(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $validated = $request->validate([
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'update_date' => ['required', 'date'],
            'stage' => ['required', 'string', 'max:50'],
            'next_action_date' => ['nullable', 'date', 'after_or_equal:update_date'],
            'details' => ['required', 'string'],
        ]);

        $validated['legal_case_id'] = $legalCase->id;
        $validated['created_by'] = optional(auth()->user())->id;

        LegalCaseUpdate::create($validated);

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تم حفظ تحديث القضية.');
    }

    public function closeCase(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $validated = $request->validate([
            'closure_notes' => ['nullable', 'string'],
            'closed_at' => ['nullable', 'date'],
        ]);

        $legalCase->update([
            'status' => 'closed',
            'closed_at' => $validated['closed_at'] ?? now()->toDateString(),
            'closure_notes' => $validated['closure_notes'] ?? null,
        ]);

        LegalCaseAssignment::query()
            ->where('legal_case_id', $legalCase->id)
            ->where('status', 'active')
            ->update(['status' => 'completed']);

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تم إغلاق القضية بنجاح.');
    }

    public function uploadDocument(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'document_file' => ['required', 'file', 'max:10240'],
        ]);

        if (!empty($validated['legal_case_update_id'])) {
            $updateBelongsToCase = LegalCaseUpdate::query()
                ->where('id', $validated['legal_case_update_id'])
                ->where('legal_case_id', $legalCase->id)
                ->exists();

            if (!$updateBelongsToCase) {
                return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'التحديث المختار لا ينتمي إلى القضية.');
            }
        }

        $file = $request->file('document_file');
        $storedPath = $file->store('strategic-finance/legal-documents', 'public');

        LegalCaseDocument::create([
            'legal_case_id' => $legalCase->id,
            'legal_case_update_id' => $validated['legal_case_update_id'] ?? null,
            'title' => $validated['title'],
            'file_original_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => optional(auth()->user())->id,
        ]);

        return redirect()->route('admin.strategic-finance.legal.index')->with('status', 'تم رفع المستند القانوني بنجاح.');
    }

    public function downloadDocument(LegalCaseDocument $document)
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($document->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        return response()->download(
            storage_path('app/public/' . $document->file_path),
            $document->file_original_name
        );
    }
}
// تم حذف هذا الملف بناءً على طلب الإدارة
