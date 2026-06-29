<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Gate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class AuditLogsController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('audit_log_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = AuditLog::query()->select(sprintf('%s.*', (new AuditLog())->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'audit_log_show';
                $editGate = 'audit_log_edit';
                $deleteGate = 'audit_log_delete';
                $crudRoutePart = 'audit-logs';

                return view('partials.datatablesActions', compact(
                'viewGate',
                'editGate',
                'deleteGate',
                'crudRoutePart',
                'row'
            ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('action', function ($row) {
                $actions = [
                    'audit:created' => '<span class="badge badge-success">إنشاء</span>',
                    'audit:updated' => '<span class="badge badge-info">تحديث</span>',
                    'audit:deleted' => '<span class="badge badge-danger">حذف</span>',
                    'audit:restored' => '<span class="badge badge-warning">استرجاع</span>',
                    'security:hard-delete' => '<span class="badge badge-dark">حذف نهائي</span>',
                ];
                return $actions[$row->action] ?? '<span class="badge badge-secondary">'.($row->action ?? 'غير محدد').'</span>';
            });
            $table->editColumn('description', function ($row) {
                return $row->description ? $row->description : '';
            });
            $table->editColumn('subject_type', function ($row) {
                if (!$row->subject_type) return '';
                $shortType = class_basename($row->subject_type);
                return '<span class="text-muted" title="'.$row->subject_type.'">'.$shortType.'</span>';
            });
            $table->editColumn('user_id', function ($row) {
                if (!$row->user_id) return '<span class="text-muted">سيستم</span>';
                $user = \App\Models\User::find($row->user_id);
                return $user ? $user->name : '<span class="text-danger">محذوف</span>';
            });
            $table->editColumn('created_at', function ($row) {
                return $row->created_at ? $row->created_at->diffForHumans() : '';
            });

            $table->rawColumns(['actions', 'placeholder', 'action', 'subject_type', 'user_id', 'created_at']);

            return $table->make(true);
        }

        // جلب الإحصائيات
        $stats = $this->getStats();

        return view('admin.auditLogs.index', compact('stats'));
    }

    private function getStats()
    {
        $total = AuditLog::count();
        $today = AuditLog::whereDate('created_at', today())->count();
        $last24h = AuditLog::where('created_at', '>=', now()->subHours(24))->count();
        $last7d = AuditLog::where('created_at', '>=', now()->subDays(7))->count();
        
        $actions = AuditLog::selectRaw('action, count(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        $users = AuditLog::whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                $user = \App\Models\User::find($log->user_id);
                return [
                    'name' => $user ? $user->name : 'محذوف',
                    'count' => $log->count,
                ];
            });

        return compact('total', 'today', 'last24h', 'last7d', 'actions', 'users');
    }

    public function show(AuditLog $auditLog)
    {
        abort_if(Gate::denies('audit_log_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.auditLogs.show', compact('auditLog'));
    }

    public function restoreDeleted(AuditLog $auditLog)
    {
        abort_if(Gate::denies('audit_log_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ((int) $auditLog->subject_id <= 0) {
            return redirect()->route('admin.audit-logs.show', $auditLog->id)
                ->with('error', 'سجل التدقيق لا يحتوي على معرف صالح للاسترجاع.');
        }

        Artisan::call('security:restore-deleted', [
            '--audit-log-id' => $auditLog->id,
        ]);

        if (Artisan::output() && stripos(Artisan::output(), 'error') !== false) {
            return redirect()->route('admin.audit-logs.show', $auditLog->id)
                ->with('error', trim(Artisan::output()));
        }

        return redirect()->route('admin.audit-logs.show', $auditLog->id)
            ->with('message', 'تمت محاولة الاسترجاع بنجاح. راجع السجل للتأكيد.');
    }
}
