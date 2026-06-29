<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\DataCleaningService;
use App\Services\TransactionAnalysisService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::orderByDesc('date')->paginate(30);
        return view('admin.strategic-finance.transactions', compact('transactions'));
    }

    public function show($id)
    {
        $transaction = Transaction::findOrFail($id);
        return view('admin.strategic-finance.transaction_show', compact('transaction'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file)[0];
        $header = array_map(fn($h) => trim(mb_strtolower($h)), $data[0]);
        unset($data[0]);

        $cleaner = new DataCleaningService();
        $analyzer = new TransactionAnalysisService();

        // تم تعطيل الربط بقسم التمويل الاستراتيجي
        try {
            foreach ($data as $row) {
                $row = array_combine($header, $row);
                $clean = $cleaner->cleanDescription($row['تفاصيل العملية'] ?? '');
                $transaction = Transaction::create([
                    'balance' => $row['الرصيد'] ?? null,
                    'debit' => $row['مدين'] ?? null,
                    'credit' => $row['دائن'] ?? null,
                    'transaction_description' => $clean['cleaned_description'] ?? ($row['تفاصيل العملية'] ?? null),
                    'beneficiary_name' => $row['اسم المستفيد'] ?? null,
                    'date' => $row['التاريخ الميلادي'] ?? null,
                    'operation_type' => $clean['operation_type'] ?? null,
                    'country' => $clean['country'] ?? null,
                    'risk_flag' => $clean['risk_flag'] ?? false,
                ]);
                $analyzer->analyze($transaction);
            }
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
        } catch (\Exception $e) {
            // تم تعطيل الربط بقسم التمويل الاستراتيجي
            return back()->withErrors(['file' => 'خطأ في الاستيراد: ' . $e->getMessage()]);
        }
        return redirect()->route('transactions.index')->with('success', 'تم الاستيراد بنجاح');
    }
}