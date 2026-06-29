<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrategicFinance\LegacyArchiveBatch;
use App\Models\StrategicFinance\LegacyArchiveFile;
use App\Models\StrategicFinance\LegacyCompany;
use App\Models\StrategicFinance\LegalCase;
use App\Models\StrategicFinance\LegalCaseUpdate;
use App\Models\StrategicFinance\Obligation;
use App\Models\StrategicFinance\Settlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StrategicLegacyRecordsController extends Controller
{
    /**
     * رفع ملف كبير على أجزاء (chunked upload) عبر Resumable.js
     */
    public function chunkUpload(Request $request)
    {
        // إعدادات
        $tempDir = storage_path('app/public/strategic-archives-chunks');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $resumableIdentifier = $request->input('resumableIdentifier');
        $resumableFilename = $request->input('resumableFilename');
        $resumableChunkNumber = $request->input('resumableChunkNumber');
        $resumableTotalChunks = $request->input('resumableTotalChunks');

        // اسم ملف الجزء المؤقت
        $chunkFile = $tempDir . DIRECTORY_SEPARATOR . $resumableIdentifier . '.part' . $resumableChunkNumber;

        // حفظ الجزء
        if ($request->hasFile('file')) {
            $request->file('file')->move($tempDir, $resumableIdentifier . '.part' . $resumableChunkNumber);
        } else {
            return response('No file', 400);
        }

        // تحقق هل كل الأجزاء وصلت
        $allChunksUploaded = true;
        for ($i = 1; $i <= $resumableTotalChunks; $i++) {
            if (!file_exists($tempDir . DIRECTORY_SEPARATOR . $resumableIdentifier . '.part' . $i)) {
                $allChunksUploaded = false;
                break;
            }
        }

        // إذا كل الأجزاء وصلت، دمجها في ملف واحد نهائي
        if ($allChunksUploaded) {
            $finalPath = storage_path('app/public/strategic-archives/' . $resumableFilename);
            if (!is_dir(dirname($finalPath))) {
                mkdir(dirname($finalPath), 0777, true);
            }
            $out = fopen($finalPath, 'wb');
            for ($i = 1; $i <= $resumableTotalChunks; $i++) {
                $chunk = file_get_contents($tempDir . DIRECTORY_SEPARATOR . $resumableIdentifier . '.part' . $i);
                fwrite($out, $chunk);
                unlink($tempDir . DIRECTORY_SEPARATOR . $resumableIdentifier . '.part' . $i);
            }
            fclose($out);
            // يمكن هنا حفظ الملف في قاعدة البيانات أو ربطه بتجميعة أرشيفية لاحقًا
            return response('تم رفع الملف وتجميعه بنجاح', 200);
        }

        // إذا لم تكتمل بعد
        return response('تم رفع جزء من الملف', 200);
    }
    public function records()
    {
        $summary = [
            'obligations_total' => (int) Obligation::count(),
            'obligations_remaining' => (float) Obligation::sum('outstanding_amount'),
            'settlements_total' => (float) Settlement::sum('amount'),
            'legal_cases_total' => (int) LegalCase::count(),
            'legal_updates_total' => (int) LegalCaseUpdate::count(),
        ];

        $recentSettlements = Settlement::query()
            ->with('obligation:id,reference_code,title')
            ->latest('settlement_date')
            ->limit(15)
            ->get();

        $recentLegalUpdates = LegalCaseUpdate::query()
            ->with(['legalCase:id,case_number,title', 'lawyer:id,full_name'])
            ->latest('update_date')
            ->limit(15)
            ->get();

        $obligationsByCompany = Obligation::query()
            ->with('bankruptCompany:id,name')
            ->selectRaw('bankrupt_company_id, COUNT(*) as total_items, SUM(outstanding_amount) as total_outstanding')
            ->groupBy('bankrupt_company_id')
            ->orderByDesc('total_outstanding')
            ->get();

        return view('admin.strategic-finance.records', [
            'summary' => $summary,
            'recentSettlements' => $recentSettlements,
            'recentLegalUpdates' => $recentLegalUpdates,
            'obligationsByCompany' => $obligationsByCompany,
        ]);
    }

    public function companyShow(LegacyCompany $company)
    {
        $obligations = Obligation::query()
            ->with(['counterparty:id,name', 'settlements' => function ($query) {
                $query->latest('settlement_date')->latest('id');
            }])
            ->where('bankrupt_company_id', $company->id)
            ->latest('due_date')
            ->latest('id')
            ->paginate(20);

        $stats = [
            'obligations_total' => (int) Obligation::query()->where('bankrupt_company_id', $company->id)->count(),
            'obligations_original' => (float) Obligation::query()->where('bankrupt_company_id', $company->id)->sum('original_amount'),
            'obligations_remaining' => (float) Obligation::query()->where('bankrupt_company_id', $company->id)->sum('outstanding_amount'),
            'settlements_total' => (float) Settlement::query()
                ->whereHas('obligation', function ($query) use ($company) {
                    $query->where('bankrupt_company_id', $company->id);
                })
                ->sum('amount'),
        ];

        $recentSettlements = Settlement::query()
            ->with('obligation:id,reference_code,title')
            ->whereHas('obligation', function ($query) use ($company) {
                $query->where('bankrupt_company_id', $company->id);
            })
            ->latest('settlement_date')
            ->latest('id')
            ->limit(10)
            ->get();

        $archiveBatches = LegacyArchiveBatch::query()
            ->with(['files' => function ($query) {
                $query->latest('id');
            }])
            ->where('legacy_company_id', $company->id)
            ->latest('archive_date')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.strategic-finance.records-company', [
            'company' => $company,
            'stats' => $stats,
            'obligations' => $obligations,
            'recentSettlements' => $recentSettlements,
            'archiveBatches' => $archiveBatches,
        ]);
    }

    public function archives()
    {
        $companies = LegacyCompany::query()
            ->withCount('obligations')
            ->withSum('obligations as obligations_outstanding_sum', 'outstanding_amount')
            ->orderBy('name')
            ->get();

        $batches = LegacyArchiveBatch::query()
            ->with(['legacyCompany:id,name', 'files' => function ($query) {
                $query->latest('id');
            }])
            ->latest('archive_date')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('admin.strategic-finance.archives', [
            'companies' => $companies,
            'batches' => $batches,
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'founded_at' => ['nullable', 'date'],
            'bankrupt_at' => ['nullable', 'date', 'after_or_equal:founded_at'],
            'ceased_activity_at' => ['nullable', 'date', 'after_or_equal:bankrupt_at'],
            'company_milestones' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        LegacyCompany::updateOrCreate(
            ['name' => $validated['name']],
            [
                'owner_name' => $validated['owner_name'] ?? null,
                'founded_at' => $validated['founded_at'] ?? null,
                'bankrupt_at' => $validated['bankrupt_at'] ?? null,
                'ceased_activity_at' => $validated['ceased_activity_at'] ?? null,
                'company_milestones' => $validated['company_milestones'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->route('admin.strategic-finance.archives.index')->with('status', 'تم حفظ الشركة السابقة بنجاح.');
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            'archive_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            // files فقط إذا لم يكن هناك رفع ملف كبير
            'files' => [
                function ($attribute, $value, $fail) use ($request) {
                    // إذا لم يتم رفع ملف تقليدي ولا ملف كبير chunked
                    $hasClassic = is_array($value) && count($value) > 0;
                    $hasChunked = $request->input('chunked_upload_complete') === '1';
                    if (!$hasClassic && !$hasChunked) {
                        $fail('يجب رفع ملف أو أكثر.');
                    }
                },
                'array',
            ],
            'files.*' => ['file', 'max:20480'],
        ]);

        $batch = LegacyArchiveBatch::create([
            'title' => $validated['title'],
            'legacy_company_id' => $validated['legacy_company_id'] ?? null,
            'archive_date' => $validated['archive_date'] ?? now()->toDateString(),
            'description' => $validated['description'] ?? null,
            'created_by' => optional(auth()->user())->id,
        ]);

        foreach ($request->file('files', []) as $index => $file) {
            $storedPath = $file->store('strategic-finance/legacy-archives', 'public');

            LegacyArchiveFile::create([
                'batch_id' => $batch->id,
                'legacy_company_id' => $validated['legacy_company_id'] ?? null,
                'title' => $validated['title'] . ' - ملف ' . ($index + 1),
                'file_original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => optional(auth()->user())->id,
            ]);
        }

        return redirect()->route('admin.strategic-finance.archives.index')->with('status', 'تم رفع تجميعة الأرشيف بنجاح.');
    }

    public function downloadFile(LegacyArchiveFile $file)
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($file->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        return response()->download(storage_path('app/public/' . $file->file_path), $file->file_original_name);
    }
}
// تم حذف هذا الملف بناءً على طلب الإدارة
