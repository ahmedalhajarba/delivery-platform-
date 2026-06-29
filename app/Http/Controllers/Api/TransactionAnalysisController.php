<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::all();
        return response()->json($transactions);
    }
}
