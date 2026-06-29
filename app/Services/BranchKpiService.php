<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchDailyConsumption;
use App\Models\BranchKpiSnapshot;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BranchKpiService
{
    public function refreshRange(Carbon $from, Carbon $to, ?int $branchId = null): void
    {
        $branches = Branch::query()
            ->when($branchId, fn (Builder $q) => $q->where('id', $branchId))
            ->whereNull('deleted_at')
            ->get();

        foreach ($branches as $branch) {
            $date = $from->copy();
            while ($date->lte($to)) {
                $this->buildDailySnapshot($branch, $date);
                $date->addDay();
            }
        }
    }

    public function getDashboardData(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $snapshots = BranchKpiSnapshot::query()
            ->with('branch')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->get();

        $byBranch = $snapshots
            ->groupBy('branch_id')
            ->map(function (Collection $items) {
                $first = $items->first();
                return [
                    'branch_id' => $first->branch_id,
                    'branch_name' => $first->branch->title_ar ?? '—',
                    'days' => $items->count(),
                    'total_orders' => (int) $items->sum('total_orders'),
                    'delivered_orders' => (int) $items->sum('delivered_orders'),
                    'avg_delivery_rate' => round((float) $items->avg('delivery_rate'), 2),
                    'avg_on_time_rate' => round((float) $items->avg('on_time_rate'), 2),
                    'avg_resolution_rate' => round((float) $items->avg('resolution_rate'), 2),
                    'avg_fleet_ready_rate' => round((float) $items->avg('fleet_ready_rate'), 2),
                    'avg_capacity_utilization_rate' => round((float) $items->avg('capacity_utilization_rate'), 2),
                    'avg_score' => round((float) $items->avg('score'), 2),
                    'last_grade' => (string) ($items->sortByDesc('date')->first()->grade ?? '—'),
                ];
            })
            ->values()
            ->sortByDesc('avg_score')
            ->values();

        $overview = [
            'branches_count' => $byBranch->count(),
            'avg_score' => round((float) $byBranch->avg('avg_score'), 2),
            'avg_delivery_rate' => round((float) $byBranch->avg('avg_delivery_rate'), 2),
            'avg_on_time_rate' => round((float) $byBranch->avg('avg_on_time_rate'), 2),
            'avg_resolution_rate' => round((float) $byBranch->avg('avg_resolution_rate'), 2),
        ];

        return [
            'overview' => $overview,
            'rows' => $byBranch,
        ];
    }

    private function buildDailySnapshot(Branch $branch, Carbon $date): BranchKpiSnapshot
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $ordersQuery = Order::query()
            ->where('origin_branch_id', $branch->id)
            ->whereBetween('created_at', [$start, $end]);

        $totalOrders = (int) (clone $ordersQuery)->count();

        $deliveredOrders = (int) (clone $ordersQuery)
            ->whereHas('order_status', function (Builder $q) {
                $q->whereIn('name_en', ['Delivered', 'delivered'])
                    ->orWhereIn('name_ar', ['تم التوصيل', 'تم التسليم']);
            })
            ->count();

        $onTimeDelivered = (int) (clone $ordersQuery)
            ->whereHas('order_status', function (Builder $q) {
                $q->whereIn('name_en', ['Delivered', 'delivered'])
                    ->orWhereIn('name_ar', ['تم التوصيل', 'تم التسليم']);
            })
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, updated_at) <= 24')
            ->count();

        $deliveryRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0;
        $onTimeRate = $deliveredOrders > 0 ? round(($onTimeDelivered / $deliveredOrders) * 100, 2) : 0;

        $ticketsQuery = SupportTicket::query()
            ->whereHas('order', function (Builder $q) use ($branch) {
                $q->where('origin_branch_id', $branch->id);
            })
            ->whereBetween('created_at', [$start, $end]);

        $totalTickets = (int) (clone $ticketsQuery)->count();
        $resolvedTickets = (int) (clone $ticketsQuery)
            ->whereIn('status', ['resolved', 'closed'])
            ->count();
        $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 2) : 100;

        $fleetTotal = (int) Vehicle::query()->where('branch_id', $branch->id)->count();
        $fleetReady = (int) Vehicle::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['available', 'in_use'])
            ->count();
        $fleetReadyRate = $fleetTotal > 0 ? round(($fleetReady / $fleetTotal) * 100, 2) : 100;

        $capacityUtilizationRate = 0;
        if (!empty($branch->daily_waybills_cap) && (int) $branch->daily_waybills_cap > 0) {
            $consumption = BranchDailyConsumption::query()
                ->where('branch_id', $branch->id)
                ->whereDate('date', $date->toDateString())
                ->first();

            $used = (int) (($consumption->subscription_used ?? 0) + ($consumption->regular_used ?? 0));
            $capacityUtilizationRate = round(($used / (int) $branch->daily_waybills_cap) * 100, 2);
        }

        [$score, $grade, $breakdown] = $this->scoreBranch(
            $branch,
            $totalOrders,
            $deliveryRate,
            $onTimeRate,
            $resolutionRate,
            $fleetReadyRate,
            $capacityUtilizationRate
        );

        return BranchKpiSnapshot::updateOrCreate(
            [
                'branch_id' => $branch->id,
                'date' => $date->toDateString(),
            ],
            [
                'total_orders' => $totalOrders,
                'delivered_orders' => $deliveredOrders,
                'delivery_rate' => $deliveryRate,
                'on_time_rate' => $onTimeRate,
                'total_tickets' => $totalTickets,
                'resolved_tickets' => $resolvedTickets,
                'resolution_rate' => $resolutionRate,
                'fleet_total' => $fleetTotal,
                'fleet_ready' => $fleetReady,
                'fleet_ready_rate' => $fleetReadyRate,
                'capacity_utilization_rate' => $capacityUtilizationRate,
                'score' => $score,
                'grade' => $grade,
                'score_breakdown' => $breakdown,
            ]
        );
    }

    private function scoreBranch(
        Branch $branch,
        int $totalOrders,
        float $deliveryRate,
        float $onTimeRate,
        float $resolutionRate,
        float $fleetReadyRate,
        float $capacityUtilizationRate
    ): array {
        $targetOrders = (int) ($branch->kpi_target_orders_per_day ?? 0);
        $targetDelivery = (float) ($branch->kpi_target_delivery_rate ?? 95);
        $targetOnTime = (float) ($branch->kpi_target_on_time_rate ?? 90);
        $targetResolution = (float) ($branch->kpi_target_resolution_rate ?? 90);
        $targetFleet = (float) ($branch->kpi_target_fleet_ready_rate ?? 85);

        $ordersScore = $targetOrders > 0
            ? min(100, round(($totalOrders / $targetOrders) * 100, 2))
            : ($totalOrders > 0 ? 100 : 70);
        $deliveryScore = $targetDelivery > 0 ? min(100, round(($deliveryRate / $targetDelivery) * 100, 2)) : $deliveryRate;
        $onTimeScore = $targetOnTime > 0 ? min(100, round(($onTimeRate / $targetOnTime) * 100, 2)) : $onTimeRate;
        $resolutionScore = $targetResolution > 0 ? min(100, round(($resolutionRate / $targetResolution) * 100, 2)) : $resolutionRate;
        $fleetScore = $targetFleet > 0 ? min(100, round(($fleetReadyRate / $targetFleet) * 100, 2)) : $fleetReadyRate;

        // Best operational utilization is around 75%; over/under utilization lowers performance.
        $capacityScore = max(0, round(100 - (abs($capacityUtilizationRate - 75) * 1.5), 2));

        $score = round(
            ($ordersScore * 0.20) +
            ($deliveryScore * 0.25) +
            ($onTimeScore * 0.20) +
            ($resolutionScore * 0.15) +
            ($fleetScore * 0.10) +
            ($capacityScore * 0.10),
            2
        );

        $grade = 'D';
        if ($score >= 90) {
            $grade = 'A+';
        } elseif ($score >= 85) {
            $grade = 'A';
        } elseif ($score >= 75) {
            $grade = 'B';
        } elseif ($score >= 65) {
            $grade = 'C';
        }

        return [
            $score,
            $grade,
            [
                'orders' => $ordersScore,
                'delivery' => $deliveryScore,
                'on_time' => $onTimeScore,
                'resolution' => $resolutionScore,
                'fleet' => $fleetScore,
                'capacity' => $capacityScore,
            ],
        ];
    }
}
