<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CustomerCreditApplicationExport;
use App\Http\Controllers\Controller;
use App\Models\CustomerCreditApplication;
use App\Models\CustomerEmployeeAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class CustomerCreditApplicationController extends Controller
{
    public function edit(User $customer)
    {
        abort_if(Gate::denies('customer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $application = CustomerCreditApplication::query()
            ->where('user_id', $customer->id)
            ->latest('id')
            ->first();

        if (!$application) {
            $application = new CustomerCreditApplication([
                'user_id' => $customer->id,
                'customer_profile_id' => $profile->id,
                'company_name_ar' => $profile->company_name ?: $customer->name,
                'commercial_register' => $profile->commercial_register,
                'tax_number' => $profile->tax_number,
                'contact_person_name_ar' => $profile->contact_person,
                'contact_person_phone' => $profile->contact_person_mobile ?: $customer->mobile,
                'contact_person_email' => $customer->email,
                'requested_credit_limit' => $profile->credit_limit,
                'requested_payment_cycle_days' => $profile->payment_cycle_days ?: 30,
                'bank_name' => $profile->bank_name,
                'iban' => $profile->iban,
                'bank_account_name' => $profile->bank_account_holder,
                'status' => 'draft',
            ]);
        }

        $employeeAccounts = collect($application->employee_accounts ?? [])->values();
        while ($employeeAccounts->count() < 3) {
            $employeeAccounts->push([
                'name_ar' => '',
                'name_en' => '',
                'email' => '',
                'mobile' => '',
                'role_ar' => '',
                'role_en' => '',
            ]);
        }

        $provisionedAccounts = CustomerEmployeeAccount::query()
            ->with('employee')
            ->where('customer_user_id', $customer->id)
            ->orderBy('slot_no')
            ->get();

        return view('admin.customers.credit-application.form', [
            'customer' => $customer,
            'profile' => $profile,
            'application' => $application,
            'employeeAccounts' => $employeeAccounts,
            'provisionedAccounts' => $provisionedAccounts,
        ]);
    }

    public function upsert(Request $request, User $customer)
    {
        abort_if(Gate::denies('customer_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $this->persistApplication($request, $customer);

        return redirect()
            ->route('admin.customers.credit-application.edit', $customer)
            ->with('success', 'تم حفظ نموذج فتح الحساب بنجاح.');
    }

    public function provisionAccounts(Request $request, User $customer)
    {
        abort_if(Gate::denies('customer_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        [$application] = $this->persistApplication($request, $customer);

        $roles = $customer->roles()->pluck('roles.id')->toArray();
        $accounts = collect($application->employee_accounts ?? [])->values();
        $generatedCredentials = [];

        DB::transaction(function () use ($customer, $application, $accounts, $roles, &$generatedCredentials) {
            foreach (range(0, 2) as $idx) {
                $slotNo = $idx + 1;
                $row = (array) ($accounts->get($idx) ?? []);

                $email = trim((string) ($row['email'] ?? ''));
                $mobile = trim((string) ($row['mobile'] ?? ''));
                $nameAr = trim((string) ($row['name_ar'] ?? ''));
                $nameEn = trim((string) ($row['name_en'] ?? ''));
                $displayName = $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : ('موظف ' . $slotNo . ' - ' . $customer->name));

                if ($email === '' && $mobile === '') {
                    continue;
                }

                $link = CustomerEmployeeAccount::query()
                    ->where('customer_user_id', $customer->id)
                    ->where('slot_no', $slotNo)
                    ->first();

                $employee = $link?->employee;

                if (!$employee) {
                    $employeeQuery = User::query();
                    if ($email !== '' && $mobile !== '') {
                        $employeeQuery->where(function ($q) use ($email, $mobile) {
                            $q->where('email', $email)->orWhere('mobile', $mobile);
                        });
                    } elseif ($email !== '') {
                        $employeeQuery->where('email', $email);
                    } elseif ($mobile !== '') {
                        $employeeQuery->where('mobile', $mobile);
                    }

                    $employee = $employeeQuery->first();
                }

                if (!$employee) {
                    $rawPassword = 'Emp@' . random_int(100000, 999999);
                    $employee = User::create([
                        'name' => $displayName,
                        'email' => $email !== '' ? $email : null,
                        'mobile' => $mobile !== '' ? $mobile : null,
                        'password' => $rawPassword,
                        'verified' => 1,
                        'verified_at' => now(),
                        'status' => 1,
                        'user_type' => 'customer',
                    ]);

                    $generatedCredentials[] = [
                        'slot' => $slotNo,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'mobile' => $employee->mobile,
                        'password' => $rawPassword,
                    ];
                } else {
                    $employee->update([
                        'name' => $displayName,
                        'email' => $email !== '' ? $email : $employee->email,
                        'mobile' => $mobile !== '' ? $mobile : $employee->mobile,
                        'status' => 1,
                        'user_type' => 'customer',
                    ]);
                }

                if (!empty($roles)) {
                    $employee->roles()->sync($roles);
                }

                CustomerEmployeeAccount::updateOrCreate(
                    [
                        'customer_user_id' => $customer->id,
                        'slot_no' => $slotNo,
                    ],
                    [
                        'employee_user_id' => $employee->id,
                        'credit_application_id' => $application->id,
                        'role_ar' => $row['role_ar'] ?? null,
                        'role_en' => $row['role_en'] ?? null,
                        'is_active' => true,
                        'last_provisioned_at' => now(),
                    ]
                );

                $row['employee_user_id'] = $employee->id;
                $accounts[$idx] = $row;
            }

            $application->employee_accounts = $accounts->values()->toArray();
            $application->save();
        });

        return redirect()
            ->route('admin.customers.credit-application.edit', $customer)
            ->with('success', 'تم إنشاء/تحديث حسابات الموظفين بنجاح.')
            ->with('generated_credentials', $generatedCredentials);
    }

    private function persistApplication(Request $request, User $customer): array
    {
        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);

        $data = $request->validate([
            'company_name_ar' => 'nullable|string|max:255',
            'company_name_en' => 'nullable|string|max:255',
            'trade_name_ar' => 'nullable|string|max:255',
            'trade_name_en' => 'nullable|string|max:255',
            'commercial_register' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'legal_form_ar' => 'nullable|string|max:255',
            'legal_form_en' => 'nullable|string|max:255',
            'business_activity_ar' => 'nullable|string|max:255',
            'business_activity_en' => 'nullable|string|max:255',
            'established_date' => 'nullable|date',
            'head_office_address_ar' => 'nullable|string|max:2000',
            'head_office_address_en' => 'nullable|string|max:2000',
            'city_ar' => 'nullable|string|max:255',
            'city_en' => 'nullable|string|max:255',
            'country_ar' => 'nullable|string|max:255',
            'country_en' => 'nullable|string|max:255',
            'contact_person_name_ar' => 'nullable|string|max:255',
            'contact_person_name_en' => 'nullable|string|max:255',
            'contact_person_title_ar' => 'nullable|string|max:255',
            'contact_person_title_en' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:50',
            'contact_person_email' => 'nullable|email|max:255',
            'finance_contact_name_ar' => 'nullable|string|max:255',
            'finance_contact_name_en' => 'nullable|string|max:255',
            'finance_contact_phone' => 'nullable|string|max:50',
            'finance_contact_email' => 'nullable|email|max:255',
            'requested_credit_limit' => 'nullable|numeric|min:0',
            'requested_payment_cycle_days' => 'nullable|integer|min:1|max:365',
            'bank_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'signatory_name_ar' => 'nullable|string|max:255',
            'signatory_name_en' => 'nullable|string|max:255',
            'signed_at' => 'nullable|date',
            'status' => 'nullable|in:draft,submitted,approved',
            'employee_accounts' => 'required|array|size:3',
            'employee_accounts.*.name_ar' => 'nullable|string|max:255',
            'employee_accounts.*.name_en' => 'nullable|string|max:255',
            'employee_accounts.*.email' => 'nullable|email|max:255',
            'employee_accounts.*.mobile' => 'nullable|string|max:50',
            'employee_accounts.*.role_ar' => 'nullable|string|max:255',
            'employee_accounts.*.role_en' => 'nullable|string|max:255',
        ]);

        $application = null;
        if ($request->filled('application_id')) {
            $application = CustomerCreditApplication::query()
                ->where('id', (int) $request->input('application_id'))
                ->where('user_id', $customer->id)
                ->first();
        }

        if ($application) {
            $application->fill(array_merge($data, [
                'customer_profile_id' => $profile->id,
                'updated_by' => auth()->id(),
            ]));
        } else {
            $application = new CustomerCreditApplication(array_merge($data, [
                'user_id' => $customer->id,
                'customer_profile_id' => $profile->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]));
        }

        if (!$application->application_number) {
            $application->application_number = CustomerCreditApplication::generateNumber();
        }
        $application->save();

        $profile->fill([
            'company_name' => $data['company_name_ar'] ?? $profile->company_name,
            'commercial_register' => $data['commercial_register'] ?? $profile->commercial_register,
            'tax_number' => $data['tax_number'] ?? $profile->tax_number,
            'contact_person' => $data['contact_person_name_ar'] ?? $profile->contact_person,
            'contact_person_mobile' => $data['contact_person_phone'] ?? $profile->contact_person_mobile,
            'credit_limit' => $data['requested_credit_limit'] ?? $profile->credit_limit,
            'payment_cycle_days' => $data['requested_payment_cycle_days'] ?? $profile->payment_cycle_days,
            'bank_name' => $data['bank_name'] ?? $profile->bank_name,
            'iban' => $data['iban'] ?? $profile->iban,
            'bank_account_holder' => $data['bank_account_name'] ?? $profile->bank_account_holder,
        ]);
        $profile->save();

        return [$application, $profile, $data];
    }

    public function downloadWord(User $customer)
    {
        abort_if(Gate::denies('customer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $application = $this->resolveApplication($customer);
        $html = view('admin.customers.credit-application.document', compact('customer', 'application'))->render();

        return response($html)
            ->header('Content-Type', 'application/msword; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="credit-application-' . $customer->id . '.doc"');
    }

    public function downloadPdf(User $customer)
    {
        abort_if(Gate::denies('customer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $application = $this->resolveApplication($customer);

        if (!class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            return view('admin.customers.credit-application.document', compact('customer', 'application'));
        }

        $pdf = app('dompdf.wrapper')
            ->loadView('admin.customers.credit-application.document', compact('customer', 'application'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('credit-application-' . $customer->id . '.pdf');
    }

    public function downloadExcel(User $customer)
    {
        abort_if(Gate::denies('customer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $application = $this->resolveApplication($customer);

        return Excel::download(
            new CustomerCreditApplicationExport($application),
            'credit-application-' . $customer->id . '.xlsx'
        );
    }

    private function resolveApplication(User $customer): CustomerCreditApplication
    {
        return CustomerCreditApplication::query()
            ->where('user_id', $customer->id)
            ->latest('id')
            ->firstOrFail();
    }
}
