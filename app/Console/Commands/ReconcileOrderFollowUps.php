<?php

namespace App\Console\Commands;

use App\Models\OrderFollowUp;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Services\OrderFollowUpManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOrderFollowUps extends Command
{
    protected $signature = 'orders:reconcile-follow-ups {--dry-run} {--grace-hours=}';

    protected $description = 'Create or repair reminder state for existing open orders and returns';

    public function handle(OrderFollowUpManager $manager): int
    {
        $grace = $this->option('grace-hours') ?? config('reminders.rollout_grace_hours', 24);
        $hours = filter_var($grace, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 720]]);
        if ($hours === false) {
            $this->components->error('Grace hours must be a whole number from 0 to 720.');

            return self::INVALID;
        }

        $orderCount = PurchaseOrder::query()->whereNotIn('status', PurchaseOrder::TERMINAL_STATUSES)->count();
        $returnCount = ProductReturn::query()->whereIn('status', ProductReturn::OPEN_STATUSES)->count();

        if ($this->option('dry-run')) {
            $this->components->info("Would reconcile {$orderCount} open order(s) and {$returnCount} open return(s).");

            return self::SUCCESS;
        }

        PurchaseOrder::query()->whereNotIn('status', PurchaseOrder::TERMINAL_STATUSES)
            ->orderBy('id')->chunkById(100, fn ($orders) => $orders->each(fn ($order) => DB::transaction(fn () => $manager->syncOrder($order))));
        ProductReturn::query()->whereIn('status', ProductReturn::OPEN_STATUSES)
            ->orderBy('id')->chunkById(100, fn ($returns) => $returns->each(fn ($return) => DB::transaction(fn () => $manager->syncReturn($return))));

        if ($hours > 0) {
            $minimum = now()->addHours($hours);
            OrderFollowUp::query()->where('status', OrderFollowUp::STATUS_PENDING)
                ->where(fn ($query) => $query->whereNull('next_due_at')->orWhere('next_due_at', '<', $minimum))
                ->update(['next_due_at' => $minimum]);
        }

        $this->components->info("Reconciled {$orderCount} open order(s) and {$returnCount} open return(s).");

        return self::SUCCESS;
    }
}
