<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Email;
use App\Models\EmailAccount;

class EmailViewerController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->get('account_id');
        $accounts = EmailAccount::all();
        $emails = Email::when($accountId, function($q) use ($accountId) {
            return $q->where('email_account_id', $accountId);
        })->orderByDesc('received_at')->paginate(20);
        return view('admin.email_viewer.index', compact('emails', 'accounts', 'accountId'));
    }

    public function show($id)
    {
        $email = Email::with('attachments')->findOrFail($id);
        return view('admin.email_viewer.show', compact('email'));
    }
}
