<?php

namespace App\Observers;

use App\Models\Company;
use App\Notifications\DataChangeEmailNotification;
use Illuminate\Support\Facades\Notification;

class CompanyActionObserver
{
    public function created(Company $model)
    {
        $data  = ['action' => 'created', 'model_name' => 'Company'];
        $users = \App\Models\User::whereHas('roles', function ($q) { return $q->where('title', 'Admin'); })->get();
        Notification::send($users, new DataChangeEmailNotification($data));
    }

    public function updated(Company $model)
    {
        $data  = ['action' => 'updated', 'model_name' => 'Company'];
        $users = \App\Models\User::whereHas('roles', function ($q) { return $q->where('title', 'Admin'); })->get();
        Notification::send($users, new DataChangeEmailNotification($data));
    }

    public function deleting(Company $model)
    {
        $data  = ['action' => 'deleted', 'model_name' => 'Company'];
        $users = \App\Models\User::whereHas('roles', function ($q) { return $q->where('title', 'Admin'); })->get();
        Notification::send($users, new DataChangeEmailNotification($data));
    }
}
