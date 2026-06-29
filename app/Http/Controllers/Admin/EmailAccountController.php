<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailAccount;

class EmailAccountController extends Controller
{
    public function index()
    {
        $accounts = EmailAccount::all();
        return view('admin.email_accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('admin.email_accounts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_email' => 'required|email|unique:email_accounts,user_email',
            'display_name' => 'nullable|string',
        ]);
        EmailAccount::create([
            'user_email' => $request->user_email,
            'provider' => 'gmail',
            'display_name' => $request->display_name,
        ]);
        return redirect()->route('admin.email-accounts.index')->with('success', 'تم إضافة الحساب بنجاح');
    }
}
