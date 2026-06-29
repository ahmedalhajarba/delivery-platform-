<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationApiClient;
use App\Models\IntegrationApiLog;
use App\Models\Order;
use App\Models\ServicePurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TechnicalIntegrationController extends Controller
{
    private const ALLOWED_DOWNLOADS = [
        'customer-api-package.zip',
        'customer-api.postman_collection.json',
        'customer-api.postman_environment.json',
        'customer-api-handover.md',
    ];

    public function index(Request $request)
    {
        $clients = IntegrationApiClient::query()
            ->with(['user:id,name,email,mobile', 'createdBy:id,name'])
            ->latest('id')
            ->paginate(20, ['*'], 'clients_page')
            ->appends($request->query());

        $logsQuery = IntegrationApiLog::query()
            ->with(['client:id,name', 'user:id,name'])
            ->latest('id');

        if ($request->filled('client_id')) {
            $logsQuery->where('client_id', (int) $request->input('client_id'));
        }

        if ($request->filled('status')) {
            $logsQuery->where('response_status', (int) $request->input('status'));
        }

        if ($request->filled('path')) {
            $path = trim((string) $request->input('path'));
            $logsQuery->where('request_path', 'like', '%' . $path . '%');
        }

        $logs = $logsQuery->paginate(30, ['*'], 'logs_page')->appends($request->query());

        $stats = [
            'active_clients' => IntegrationApiClient::query()->where('status', 'active')->count(),
            'inactive_clients' => IntegrationApiClient::query()->where('status', 'inactive')->count(),
            'today_requests' => IntegrationApiLog::query()->whereDate('created_at', today())->count(),
            'today_errors' => IntegrationApiLog::query()->whereDate('created_at', today())->where('response_status', '>=', 400)->count(),
            'orders_created_today' => Order::query()->whereDate('created_at', today())->count(),
            'service_purchases_today' => ServicePurchase::query()->whereDate('created_at', today())->count(),
        ];

        $customers = User::query()->select('id', 'name', 'email')->orderBy('name')->limit(300)->get();

        return view('admin.integrations.technical-api.index', compact('clients', 'logs', 'stats', 'customers'));
    }

    public function storeClient(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $plainKey = 'cli_' . Str::random(48);

        IntegrationApiClient::query()->create([
            'name' => $validated['name'],
            'user_id' => (int) $validated['user_id'],
            'key_hash' => hash('sha256', $plainKey),
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('admin.technical-integrations.index')
            ->with('success', 'تم إنشاء عميل الربط التقني بنجاح.')
            ->with('generated_client_key', $plainKey);
    }

    public function regenerateKey(IntegrationApiClient $client)
    {
        $plainKey = 'cli_' . Str::random(48);

        $client->update([
            'key_hash' => hash('sha256', $plainKey),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('admin.technical-integrations.index')
            ->with('success', 'تم إعادة توليد المفتاح بنجاح.')
            ->with('generated_client_key', $plainKey);
    }

    public function toggleStatus(IntegrationApiClient $client)
    {
        $nextStatus = $client->status === 'active' ? 'inactive' : 'active';

        $client->update([
            'status' => $nextStatus,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم تحديث حالة عميل الربط التقني.');
    }

    public function destroyClient(IntegrationApiClient $client)
    {
        $client->delete();

        return back()->with('success', 'تم حذف عميل الربط التقني.');
    }

    public function downloadPackage(string $file)
    {
        if (!in_array($file, self::ALLOWED_DOWNLOADS, true)) {
            abort(404);
        }

        $path = base_path('docs/' . $file);

        if (!File::exists($path)) {
            return back()->with('error', 'الملف المطلوب غير موجود حالياً.');
        }

        return response()->download($path);
    }
}
