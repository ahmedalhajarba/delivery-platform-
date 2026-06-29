<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class SalesCustomerDirectoryService
{
    public function listForSelection(): Collection
    {
        return User::query()
            ->with('profile')
            ->leftJoin('customer_profiles as cp', function ($join) {
                $join->on('cp.user_id', '=', 'users.id')
                    ->whereNull('cp.deleted_at');
            })
            ->where(function ($q) {
                $q->where('users.user_type', 'customer')
                    ->orWhereHas('roles', function ($roleQ) {
                        $roleQ->where('title', 'Customer');
                    });
            })
            ->select('users.*')
            ->distinct()
            ->orderByRaw("CASE WHEN cp.company_name IS NULL OR cp.company_name = '' THEN 1 ELSE 0 END")
            ->orderBy('cp.company_name')
            ->orderBy('users.name')
            ->get();
    }
}
