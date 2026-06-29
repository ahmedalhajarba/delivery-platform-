<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrategicFinance\Counterparty;

use App\Models\StrategicFinance\LegacyCompany;
use App\Models\StrategicFinance\Obligation;
use App\Models\StrategicFinance\ObligationAttachment;
use App\Models\StrategicFinance\Settlement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StrategicObligationsController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => (int) Obligation::count(),
            'open' => (int) Obligation::whereIn('status', ['open', 'in_progress'])->count(),
            'closed' => (int) Obligation::where('status', 'closed')->count(),
            'remaining' => (float) Obligation::sum('outstanding_amount'),
        ];

        $counterparties = Counterparty::query()->where('is_active', true)->orderBy('name')->get();
        $legacyCompanies = LegacyCompany::query()->orderBy('name')->get();
        $creditorSubCategories = $this->creditorSubCategoryOptions();

        $obligations = Obligation::query()
            ->with([
                'counterparty:id,name,category,sub_category',
                'bankruptCompany:id,name',
                'settlements' => function ($query) {
                    $query->latest('settlement_date')->latest('id');
                },
            ])
            ->latest('id')
            ->limit(50)
            ->get();

        $recentSettlements = Settlement::query()
            ->with([
                'obligation:id,reference_code,title,counterparty_id,bankrupt_company_id,currency_code',
                'obligation.counterparty:id,name',
                'obligation.bankruptCompany:id,name',
            ])
            ->latest('settlement_date')
            ->latest('id')
            ->limit(40)
            ->get();

        return view('admin.strategic-finance.obligations', [
            'stats' => $stats,
            'counterparties' => $counterparties,
            'legacyCompanies' => $legacyCompanies,
            'creditorSubCategories' => $creditorSubCategories,
            'obligations' => $obligations,
            'recentSettlements' => $recentSettlements,
        ]);
    }

    public function storeCounterparty(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sub_category' => ['required', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        Counterparty::updateOrCreate(
            ['name' => $validated['name']],
            [
                'category' => 'creditor_contact',
                'sub_category' => $validated['sub_category'],
                'contact_person' => $validated['contact_person'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()->route('admin.strategic-finance.obligations.index')->with('status', 'تم حفظ جهة الاتصال للدائن بنجاح.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference_code' => ['required', 'string', 'max:100', 'unique:strategic_finance.sf_obligations,reference_code'],
            'counterparty_id' => ['required', 'integer', 'exists:strategic_finance.sf_counterparties,id'],
            'bankrupt_company_id' => ['required', 'integer', 'exists:strategic_finance.sf_legacy_companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency_code' => ['required', 'string', 'size:3'],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'priority_level' => ['required', 'integer', 'min:1', 'max:5'],
            'due_date' => ['nullable', 'date'],
            'scheduled_start_date' => ['nullable', 'date'],
            'scheduled_end_date' => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
        ]);

        $counterparty = Counterparty::query()->findOrFail((int) $validated['counterparty_id']);

        $validated['category'] = $counterparty->sub_category ?: $counterparty->category;

        if ($this->isDuplicateDebt(
            (int) $validated['counterparty_id'],
            (int) $validated['bankrupt_company_id'],
            (string) $validated['title'],
            (float) $validated['original_amount'],
            $validated['due_date'] ?? null
        )) {
            return redirect()->back()->withInput()->withErrors([
                'title' => 'هذا الدين مسجل مسبقا لنفس جهة الاتصال والشركة المفلسة.',
            ]);
        }

        $validated['outstanding_amount'] = $validated['original_amount'];
        $validated['status'] = 'open';

        Obligation::create($validated);

        return redirect()->route('admin.strategic-finance.obligations.index')->with('status', 'تمت إضافة الالتزام بنجاح.');
    }

    public function contactsReport(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $companyId = $request->query('legacy_company_id');
        $subCategory = trim((string) $request->query('sub_category', ''));

        $rows = Counterparty::query()
            ->selectRaw('sf_counterparties.id, sf_counterparties.name, sf_counterparties.sub_category, sf_counterparties.contact_phone, sf_counterparties.contact_email, sf_obligations.bankrupt_company_id, COUNT(sf_obligations.id) as obligations_count, SUM(sf_obligations.original_amount) as total_original, SUM(sf_obligations.outstanding_amount) as total_outstanding')
            ->join('sf_obligations', 'sf_obligations.counterparty_id', '=', 'sf_counterparties.id')
            ->where('sf_counterparties.is_active', true)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('sf_obligations.bankrupt_company_id', (int) $companyId);
            })
            ->when($subCategory !== '', function ($query) use ($subCategory) {
                $query->where('sf_counterparties.sub_category', $subCategory);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('sf_counterparties.name', 'like', '%' . $q . '%')
                        ->orWhere('sf_counterparties.contact_phone', 'like', '%' . $q . '%')
                        ->orWhere('sf_counterparties.contact_email', 'like', '%' . $q . '%');
                });
            })
            ->groupBy(
                'sf_counterparties.id',
                'sf_counterparties.name',
                'sf_counterparties.sub_category',
                'sf_counterparties.contact_phone',
                'sf_counterparties.contact_email',
                'sf_obligations.bankrupt_company_id'
            )
            ->orderByDesc('total_outstanding')
            ->paginate(20)
            ->appends($request->query());

        $companyNames = LegacyCompany::query()->pluck('name', 'id');

        $rows->getCollection()->transform(function ($row) use ($companyNames) {
            $row->total_settled = max(((float) $row->total_original) - ((float) $row->total_outstanding), 0);
            $row->legacy_company_name = $companyNames->get((int) $row->bankrupt_company_id);

            return $row;
        });

        return view('admin.strategic-finance.obligations-contacts-report', [
            'rows' => $rows,
            'legacyCompanies' => LegacyCompany::query()->orderBy('name')->get(),
            'creditorSubCategories' => $this->creditorSubCategoryOptions(),
            'filters' => [
                'q' => $q,
                'legacy_company_id' => $companyId,
                'sub_category' => $subCategory,
            ],
        ]);
    }

    public function contactStatement(Request $request, Counterparty $counterparty)
    {
        $companyId = $request->query('legacy_company_id');

        $obligations = Obligation::query()
            ->with([
                'settlements' => function ($query) {
                    $query->latest('settlement_date')->latest('id');
                },
                'bankruptCompany:id,name',
            ])
            ->where('counterparty_id', $counterparty->id)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('bankrupt_company_id', (int) $companyId);
            })
            ->latest('due_date')
            ->latest('id')
            ->paginate(25)
            ->appends($request->query());

        $totalOriginal = (float) Obligation::query()
            ->where('counterparty_id', $counterparty->id)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('bankrupt_company_id', (int) $companyId);
            })
            ->sum('original_amount');

        $totalOutstanding = (float) Obligation::query()
            ->where('counterparty_id', $counterparty->id)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('bankrupt_company_id', (int) $companyId);
            })
            ->sum('outstanding_amount');

        return view('admin.strategic-finance.obligations-contact-statement', [
            'counterparty' => $counterparty,
            'obligations' => $obligations,
            'legacyCompanies' => LegacyCompany::query()->orderBy('name')->get(),
            'selectedCompanyId' => $companyId,
            'totalOriginal' => $totalOriginal,
            'totalOutstanding' => $totalOutstanding,
            'totalSettled' => max($totalOriginal - $totalOutstanding, 0),
        ]);
    }

    public function storeContactDebt(Request $request, Counterparty $counterparty): RedirectResponse
    {
        $validated = $request->validate([
            'legacy_company_id' => ['required', 'integer', 'exists:strategic_finance.sf_legacy_companies,id'],
            'reference_code' => ['nullable', 'string', 'max:100', 'unique:strategic_finance.sf_obligations,reference_code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'priority_level' => ['required', 'integer', 'min:1', 'max:5'],
            'due_date' => ['nullable', 'date'],
            'scheduled_start_date' => ['nullable', 'date'],
            'scheduled_end_date' => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
        ]);

        $referenceCode = $validated['reference_code'] ?? null;

        if (!$referenceCode) {
            $referenceCode = $this->generateObligationReferenceCode();
        }

        if ($this->isDuplicateDebt(
            (int) $counterparty->id,
            (int) $validated['legacy_company_id'],
            (string) $validated['title'],
            (float) $validated['original_amount'],
            $validated['due_date'] ?? null
        )) {
            return redirect()->back()->withInput()->withErrors([
                'title' => 'هذا الدين موجود مسبقا لنفس الجهة ونفس الشركة المفلسة.',
            ]);
        }

        $obligation = Obligation::create([
            'reference_code' => $referenceCode,
            'counterparty_id' => $counterparty->id,
            'bankrupt_company_id' => (int) $validated['legacy_company_id'],
            'category' => $counterparty->sub_category ?: $counterparty->category,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'currency_code' => 'SAR',
            'original_amount' => (float) $validated['original_amount'],
            'outstanding_amount' => (float) $validated['original_amount'],
            'priority_level' => (int) $validated['priority_level'],
            'status' => 'open',
            'due_date' => $validated['due_date'] ?? null,
            'scheduled_start_date' => $validated['scheduled_start_date'] ?? null,
            'scheduled_end_date' => $validated['scheduled_end_date'] ?? null,
        ]);

        return redirect()
            ->route('admin.strategic-finance.obligations.contacts.statement', [
                'counterparty' => $counterparty->id,
                'legacy_company_id' => (int) $validated['legacy_company_id'],
            ])
            ->with('status', 'تمت إضافة سجل دين جديد وربطه بجهة الاتصال والشركة المفلسة.')
            ->with('new_obligation_id', $obligation->id);
    }

    public function importContactDebts(Request $request, Counterparty $counterparty): RedirectResponse
    {
        $validated = $request->validate([
            'legacy_company_id' => ['required', 'integer', 'exists:strategic_finance.sf_legacy_companies,id'],
            'debts_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('debts_file');
        $path = $file->getRealPath();
        $handle = fopen((string) $path, 'r');

        if ($handle === false) {
            return redirect()->back()->withErrors([
                'debts_file' => 'تعذر قراءة الملف. حاول مرة أخرى.',
            ]);
        }

        $header = fgetcsv($handle);

        if (!is_array($header)) {
            fclose($handle);

            return redirect()->back()->withErrors([
                'debts_file' => 'الملف فارغ أو غير صالح.',
            ]);
        }

        $headerMap = [];

        foreach ($header as $index => $column) {
            $normalized = strtolower(trim((string) $column));
            $headerMap[$normalized] = $index;
        }

        foreach (['title', 'original_amount'] as $requiredColumn) {
            if (!array_key_exists($requiredColumn, $headerMap)) {
                fclose($handle);

                return redirect()->back()->withErrors([
                    'debts_file' => 'صيغة الملف غير صحيحة. الأعمدة المطلوبة: title, original_amount.',
                ]);
            }
        }

        $inserted = 0;
        $duplicates = 0;
        $invalid = 0;
        $legacyCompanyId = (int) $validated['legacy_company_id'];

        while (($row = fgetcsv($handle)) !== false) {
            $title = trim((string) ($row[$headerMap['title']] ?? ''));
            $amountRaw = trim((string) ($row[$headerMap['original_amount']] ?? ''));
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0;

            if ($title === '' || $amount <= 0) {
                $invalid++;
                continue;
            }

            $dueDate = $this->normalizeCsvDate($row[$headerMap['due_date']] ?? null);
            $scheduledStartDate = $this->normalizeCsvDate($row[$headerMap['scheduled_start_date']] ?? null);
            $scheduledEndDate = $this->normalizeCsvDate($row[$headerMap['scheduled_end_date']] ?? null);

            $priorityRaw = trim((string) ($row[$headerMap['priority_level']] ?? '3'));
            $priority = is_numeric($priorityRaw) ? (int) $priorityRaw : 3;
            $priority = max(1, min(5, $priority));

            if ($this->isDuplicateDebt((int) $counterparty->id, $legacyCompanyId, $title, $amount, $dueDate)) {
                $duplicates++;
                continue;
            }

            $reference = trim((string) ($row[$headerMap['reference_code']] ?? ''));
            if ($reference === '' || Obligation::query()->where('reference_code', $reference)->exists()) {
                $reference = $this->generateObligationReferenceCode();
            }

            $description = trim((string) ($row[$headerMap['description']] ?? ''));

            Obligation::create([
                'reference_code' => $reference,
                'counterparty_id' => $counterparty->id,
                'bankrupt_company_id' => $legacyCompanyId,
                'category' => $counterparty->sub_category ?: $counterparty->category,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'currency_code' => 'SAR',
                'original_amount' => $amount,
                'outstanding_amount' => $amount,
                'priority_level' => $priority,
                'status' => 'open',
                'due_date' => $dueDate,
                'scheduled_start_date' => $scheduledStartDate,
                'scheduled_end_date' => $scheduledEndDate,
            ]);

            $inserted++;
        }

        fclose($handle);

        $message = 'تم الاستيراد: ' . $inserted . ' سجل. المكرر: ' . $duplicates . '. غير الصالح: ' . $invalid . '.';

        return redirect()
            ->route('admin.strategic-finance.obligations.contacts.statement', [
                'counterparty' => $counterparty->id,
                'legacy_company_id' => $legacyCompanyId,
            ])
            ->with('status', $message);
    }

    public function show(Obligation $obligation)
    {
        $obligation->load([
            'counterparty:id,name,category,notes',
            'settlements' => function ($query) {
                $query->latest('settlement_date')->latest('id');
            },
            'attachments' => function ($query) {
                $query->latest('id');
            },
        ]);

        $totalSettled = (float) $obligation->settlements->sum('amount');

        return view('admin.strategic-finance.obligations-show', [
            'obligation' => $obligation,
            'totalSettled' => $totalSettled,
        ]);
    }

    public function storeAttachment(Request $request, Obligation $obligation): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachment_file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,xls,xlsx,csv'],
        ]);

        $file = $request->file('attachment_file');
        $storedPath = $file->store('strategic-finance/obligation-attachments', 'public');

        ObligationAttachment::create([
            'obligation_id' => $obligation->id,
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'file_original_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => optional(auth()->user())->id,
        ]);

        return redirect()->route('admin.strategic-finance.obligations.show', $obligation->id)
            ->with('status', 'تم رفع مرفق الالتزام بنجاح.');
    }

    public function downloadAttachment(Obligation $obligation, ObligationAttachment $attachment)
    {
        if ((int) $attachment->obligation_id !== (int) $obligation->id) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        return response()->download(
            storage_path('app/public/' . $attachment->file_path),
            $attachment->file_original_name
        );
    }

    public function storeSettlement(Request $request, Obligation $obligation): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'settlement_date' => ['required', 'date'],
            'settlement_type' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $amount = (float) $validated['amount'];

        // تم تعطيل الربط بقسم التمويل الاستراتيجي
            $lockedObligation = Obligation::query()->whereKey($obligation->id)->lockForUpdate()->firstOrFail();

            if ($amount > (float) $lockedObligation->outstanding_amount) {
                throw new \RuntimeException('قيمة المخالصة أكبر من الرصيد المتبقي.');
            }

            $newOutstanding = max(((float) $lockedObligation->outstanding_amount) - $amount, 0);
            $closureScope = $newOutstanding == 0.0 ? 'full' : 'partial';

            Settlement::create([
                'obligation_id' => $lockedObligation->id,
                'settlement_code' => 'STL-' . now()->format('YmdHis') . '-' . mt_rand(100, 999),
                'settlement_type' => $validated['settlement_type'],
                'amount' => $amount,
                'settlement_date' => $validated['settlement_date'],
                'status' => 'posted',
                'closure_scope' => $closureScope,
                'notes' => $validated['notes'] ?? null,
                'created_by' => optional(auth()->user())->id,
            ]);

            $lockedObligation->update([
                'outstanding_amount' => $newOutstanding,
                'status' => $newOutstanding == 0.0 ? 'closed' : 'in_progress',
            ]);
        });

        return redirect()->route('admin.strategic-finance.obligations.index')->with('status', 'تمت إضافة المخالصة وتحديث رصيد الالتزام.');
    }

    private function creditorSubCategoryOptions(): array
    {
        return [
            'employee' => 'موظف',
            'partner' => 'شريك',
            'worker' => 'عامل',
            'supplier' => 'مورد',
            'customer' => 'زبون',
            'government' => 'جهة حكومية',
            'other' => 'أخرى',
        ];
    }

    private function generateObligationReferenceCode(): string
    {
        do {
            $code = 'OBL-' . now()->format('YmdHis') . '-' . mt_rand(100, 999);
        } while (Obligation::query()->where('reference_code', $code)->exists());

        return $code;
    }

    private function isDuplicateDebt(int $counterpartyId, int $legacyCompanyId, string $title, float $amount, ?string $dueDate): bool
    {
        return Obligation::query()
            ->where('counterparty_id', $counterpartyId)
            ->where('bankrupt_company_id', $legacyCompanyId)
            ->where('title', trim($title))
            ->where('original_amount', $amount)
            ->where(function ($query) use ($dueDate) {
                if ($dueDate) {
                    $query->whereDate('due_date', $dueDate);
                } else {
                    $query->whereNull('due_date');
                }
            })
            ->exists();
    }

    private function normalizeCsvDate($raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function bulkClassifyContacts(Request $request): RedirectResponse
    {
        \Log::info('bulkClassifyContacts called', ['input' => $request->all()]);
        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'exists:strategic_finance.sf_counterparties,id'],
            'sub_category' => ['required', 'string', 'max:50'],
        ]);
        \Log::info('bulkClassifyContacts validated', ['validated' => $validated]);

        Counterparty::whereIn('id', $validated['contact_ids'])
            ->update(['sub_category' => $validated['sub_category']]);

        \Log::info('bulkClassifyContacts updated', ['ids' => $validated['contact_ids'], 'sub_category' => $validated['sub_category']]);
        return back()->with('status', 'تم تحديث التصنيف الفرعي بنجاح للجهات المحددة.');
    }
}
// تم حذف هذا الملف بناءً على طلب الإدارة
