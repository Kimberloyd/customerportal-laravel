<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardOverview
{
    public function build(Builder $orders, int $days, bool $isCustomer): array
    {
        $now = CarbonImmutable::now('UTC');
        $start = $now->startOfDay()->subDays($days - 1);
        $previousStart = $start->subDays($days);
        $currentOrders = (clone $orders)->whereBetween('submitted_at', [$start, $now]);
        $previousOrders = (clone $orders)->where('submitted_at', '>=', $previousStart)->where('submitted_at', '<', $start);

        $daily = (clone $orders)->whereBetween('submitted_at', [$previousStart, $now])
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(submitted_at)')->toBase()->pluck('total', 'day');
        $trend = [];
        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day)->toDateString();
            $previousDate = $previousStart->addDays($day)->toDateString();
            $trend[] = [
                'date' => $date,
                'previous_date' => $previousDate,
                'current' => (int) ($daily[$date] ?? 0),
                'previous' => (int) ($daily[$previousDate] ?? 0),
            ];
        }

        $attention = (clone $orders)->where(function (Builder $query) use ($isCustomer) {
            $query->whereNotIn('status', PurchaseOrder::TERMINAL_STATUSES);
            if ($isCustomer) {
                $query->orWhere(fn (Builder $query) => $query->where('status', PurchaseOrder::STATUS_COMPLETED)->whereNull('customer_received_at'));
            }
        });
        $attentionCount = (clone $attention)->count();
        $attention->orderByRaw($isCustomer
            ? "CASE WHEN status = 'completed' THEN 0 WHEN status IN ('partial', 'processing') THEN 1 ELSE 2 END"
            : "CASE WHEN status = 'submitted' THEN 0 WHEN status = 'reviewing' THEN 1 ELSE 2 END")
            ->orderBy('submitted_at')->orderBy('id');

        return [
            'period' => $days,
            'start' => $start->toDateString(),
            'end' => $now->toDateString(),
            'updated_at' => $now->toIso8601String(),
            'current' => $this->summary($currentOrders),
            'previous' => $this->summary($previousOrders),
            'trend' => $trend,
            'attention_count' => $attentionCount,
            'attention' => $this->orderRows($attention),
            'recent' => $this->orderRows((clone $currentOrders)->orderByDesc('submitted_at')->orderByDesc('id')),
        ];
    }

    private function summary(Builder $orders): array
    {
        $statuses = (clone $orders)->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');
        $eligible = (clone $orders)->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)->select('id');
        $items = PurchaseOrderItem::query()->whereIn('purchase_order_id', $eligible)
            ->selectRaw('COALESCE(SUM(line_total), 0) as value, COALESCE(SUM(quantity), 0) as ordered,
                COALESCE(SUM(CASE WHEN delivered_quantity > quantity THEN quantity ELSE COALESCE(delivered_quantity, 0) END), 0) as delivered')
            ->first();

        return [
            'orders' => (int) $statuses->sum(),
            'value' => (float) $items->value,
            'ordered_units' => (int) $items->ordered,
            'delivered_units' => (int) $items->delivered,
            'fulfillment' => $items->ordered > 0 ? round($items->delivered / $items->ordered * 100, 1) : null,
            'completed' => (int) ($statuses[PurchaseOrder::STATUS_COMPLETED] ?? 0),
            'stages' => [
                'review' => (int) ($statuses[PurchaseOrder::STATUS_SUBMITTED] ?? 0) + (int) ($statuses[PurchaseOrder::STATUS_REVIEWING] ?? 0),
                'fulfillment' => (int) ($statuses[PurchaseOrder::STATUS_PARTIAL] ?? 0) + (int) ($statuses[PurchaseOrder::STATUS_PROCESSING] ?? 0),
                'completed' => (int) ($statuses[PurchaseOrder::STATUS_COMPLETED] ?? 0),
                'cancelled' => (int) ($statuses[PurchaseOrder::STATUS_CANCELLED] ?? 0),
            ],
        ];
    }

    private function orderRows(Builder $orders): array
    {
        return $orders->with('customer:id,company_name')->withSum('items as ordered_units', 'quantity')
            ->withSum('items as delivered_units', 'delivered_quantity')->limit(4)->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'customer_name' => $order->customer?->company_name,
                'status' => $order->status,
                'received' => $order->customer_received_at !== null,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'ordered_units' => (int) $order->ordered_units,
                'delivered_units' => (int) $order->delivered_units,
            ])->all();
    }
}
