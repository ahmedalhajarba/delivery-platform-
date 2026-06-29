<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailAccount;
use App\Services\MboxImportService;

class EmailImportController extends Controller
{
    public function showImportForm()
    {
        $accounts = EmailAccount::all();
        return view('admin.email_import.form', compact('accounts'));
    }

    public function import(Request $request, MboxImportService $importService)
    {
        $request->validate([
            'mbox_file' => 'required|file',
            'email_account_id' => 'required|exists:email_accounts,id',
        ]);
        $file = $request->file('mbox_file');
        $path = $file->storeAs('mbox_uploads', uniqid() . '_' . $file->getClientOriginalName());
        $count = $importService->importFromMbox(storage_path('app/' . $path), $request->email_account_id);
        return redirect()->back()->with('success', "تم استيراد {$count} رسالة بنجاح.");
    }
}
