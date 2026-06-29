<?php

namespace App\Services\CallCenter;

use App\Models\User;
use App\Services\Validation\ContactValidation;
use Illuminate\Http\Request;

class CallerLookupService
{
    public function findCustomerByPhone(?string $phone): ?User
    {
        $normalized = ContactValidation::normalizeLocalNumber($phone);
        if (!$normalized) {
            return null;
        }

        return User::query()
            ->where(function ($q) {
                $q->where('user_type', 'customer')
                    ->orWhereHas('roles', function ($roleQ) {
                        $roleQ->where('title', 'Customer');
                    });
            })
            ->whereNotNull('mobile')
            ->where(function ($q) use ($normalized) {
                $q->where('mobile', $normalized)
                    ->orWhere('mobile', 'like', '%' . $normalized);
            })
            ->orderByRaw('CASE WHEN mobile = ? THEN 0 ELSE 1 END', [$normalized])
            ->first();
    }

    public function buildScreenPopContext(Request $request, ?User $customer = null): array
    {
        $phone = (string) $request->input('phone', $request->input('caller_phone', ''));
        $callId = (string) $request->input('call_id', '');
        $direction = (string) $request->input('direction', 'inbound');

        $query = array_filter([
            'phone' => $phone !== '' ? $phone : null,
            'call_id' => $callId !== '' ? $callId : null,
            'direction' => $direction !== '' ? $direction : null,
            'user_id' => $customer?->id,
            'subject' => $this->buildSubject($phone, $direction),
            'category' => 'other',
            'priority' => 'high',
            'requester_department' => 'customer_service',
            'request_context' => 'customer_profile',
            'description' => $this->buildDescription($phone, $callId, $direction),
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'customer_found' => (bool) $customer,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'email' => $customer->email,
                'profile_url' => route('admin.customers.show', $customer),
            ] : null,
            'ticket_create_url' => route('admin.support-tickets.create', $query),
        ];
    }

    private function buildSubject(string $phone, string $direction): string
    {
        $prefix = $direction === 'outbound' ? 'مكالمة صادرة' : 'مكالمة واردة';

        return trim($prefix . ($phone !== '' ? ' - ' . $phone : ''));
    }

    private function buildDescription(string $phone, string $callId, string $direction): string
    {
        $lines = [
            'تم إنشاء التذكرة من تكامل مركز الاتصال (ICTCRM).',
        ];

        if ($phone !== '') {
            $lines[] = 'رقم المتصل: ' . $phone;
        }
        if ($callId !== '') {
            $lines[] = 'معرّف المكالمة: ' . $callId;
        }
        if ($direction !== '') {
            $lines[] = 'اتجاه المكالمة: ' . $direction;
        }

        return implode("\n", $lines);
    }
}
