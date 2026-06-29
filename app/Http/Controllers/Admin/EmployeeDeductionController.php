<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeDeductionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeDeduction::with('user')->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->is_active === 1);
        }

        $deductions = $query->paginate(20)->appends($request->query());

        $employees = User::query()
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '!=', 'customer');
            })
            ->whereHas('roles', function ($q) {
                $q->whereNotIn('title', ['customer']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_position']);

        return view('admin.employee-deductions.index', compact('deductions', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'calculation_type' => ['required', 'in:fixed,percentage'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['calculation_type'] === 'percentage' && empty($data['percentage'])) {
            return back()->withInput()->withErrors(['percentage' => 'نسبة الخصم مطلوبة عند اختيار نوع النسبة.']);
        }

        EmployeeDeduction::create([
            'user_id' => (int) $data['user_id'],
            'title' => (string) $data['title'],
            'calculation_type' => (string) $data['calculation_type'],
            'amount' => (float) ($data['amount'] ?? 0),
            'percentage' => $data['percentage'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_recurring' => (bool) ($data['is_recurring'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'تمت إضافة الخصم للموظف بنجاح.');
    }

    public function toggle(EmployeeDeduction $employeeDeduction)
    {
        $employeeDeduction->update([
            'is_active' => !$employeeDeduction->is_active,
        ]);

        return back()->with('success', 'تم تحديث حالة الخصم.');
    }
}
