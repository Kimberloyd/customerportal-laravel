<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\SearchSelection;
use App\Models\User;
use App\Services\OrderFollowUpManager;
use App\Support\OrderAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Exercises the real business logic behind the app's core flows against
 * whatever database this command is run against -- deliberately at the
 * model/service layer (the same code controllers call), not over real
 * HTTP, so it doesn't need to fight CSRF/session plumbing to be reliable.
 * Every record it creates is prefixed 'SMOKETEST-' so it's unmistakable
 * even if a run crashes before cleanup finishes; --cleanup-only sweeps
 * up anything a previous interrupted run left behind.
 *
 * Does NOT verify actual outbound SMS/Messenger/email delivery -- those
 * stay behind their existing PO_NOTIFICATIONS_*_ENABLED flags exactly as
 * configured, so this never sends a real message to a real customer.
 */
#[Signature('system:smoke-test {--cleanup-only : Only remove leftover SMOKETEST- records from a previous interrupted run, run no checks}')]
#[Description('End-to-end check of core flows (auth, orders, messaging, search) with automatic cleanup')]
class SmokeTestProduction extends Command
{
    private const TAG = 'SMOKETEST-';

    /** @var array<int, array{step: string, ok: bool, detail: string}> */
    private array $results = [];

    private ?User $testStaff = null;

    private ?Customer $testCustomer = null;

    private ?PurchaseOrder $testOrder = null;

    public function handle(OrderFollowUpManager $followUps): int
    {
        if ($this->option('cleanup-only')) {
            $this->cleanupByTag();

            return self::SUCCESS;
        }

        $runId = self::TAG.now()->format('YmdHis');

        try {
            $this->checkInfrastructure();
            $this->checkAuth($runId);
            $this->checkOrderLifecycle($runId, $followUps);
            $this->checkMessaging($runId);
            $this->checkSearchTracking($runId);
        } catch (Throwable $e) {
            $this->results[] = ['step' => 'unexpected exception', 'ok' => false, 'detail' => $e->getMessage()];
        } finally {
            Auth::logout();
            $this->cleanup();
        }

        return $this->report();
    }

    private function checkInfrastructure(): void
    {
        $this->step('Database connectivity', function () {
            DB::connection()->getPdo();

            return 'connected';
        });

        $this->step('Redis connectivity', function () {
            $pong = Redis::connection()->ping();

            return "ping: {$pong}";
        });

        $this->step('Cache read/write', function () {
            $key = self::TAG.'cache-probe';
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'ok') {
                throw new \RuntimeException('cache did not round-trip the value it was given');
            }

            return 'round-tripped';
        });
    }

    private function checkAuth(string $runId): void
    {
        $this->step('Create test staff account and verify password hashing', function () use ($runId) {
            $password = 'SmokeTest-'.bin2hex(random_bytes(8)).'!';

            $this->testStaff = User::create([
                'full_name' => $runId.' Staff',
                'email' => strtolower($runId).'-staff@smoketest.invalid',
                'password_hash' => Hash::make($password),
                'role' => User::ROLE_AGENT,
                'is_active' => true,
                'session_version' => 0,
            ]);

            if (! Hash::check($password, $this->testStaff->password_hash)) {
                throw new \RuntimeException('Hash::check failed against the hash Hash::make just produced');
            }

            Auth::login($this->testStaff);

            if (Auth::id() !== $this->testStaff->id) {
                throw new \RuntimeException('Auth::login did not authenticate as the created user');
            }

            return "user #{$this->testStaff->id} created, hashed, verified, authenticated";
        });
    }

    private function checkOrderLifecycle(string $runId, OrderFollowUpManager $followUps): void
    {
        $this->step('Create test customer', function () use ($runId) {
            $this->testCustomer = Customer::create([
                'company_name' => $runId.' Co',
                'channel' => 'direct',
                'is_active' => true,
            ]);

            return "customer #{$this->testCustomer->id} created";
        });

        $this->step('Create order and verify default status', function () use ($runId) {
            $this->testOrder = PurchaseOrder::create([
                'po_number' => $runId,
                'customer_id' => $this->testCustomer->id,
                'status' => PurchaseOrder::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            PurchaseOrderItem::create([
                'purchase_order_id' => $this->testOrder->id,
                'product_name' => self::TAG.'Product',
                'sku' => self::TAG.'SKU',
                'quantity' => 10,
                'delivered_quantity' => 0,
                'unit_price' => 1.00,
                'line_total' => 10.00,
            ]);

            $this->testOrder->refresh();

            if ($this->testOrder->status !== PurchaseOrder::STATUS_SUBMITTED) {
                throw new \RuntimeException("expected status '".PurchaseOrder::STATUS_SUBMITTED."', got '{$this->testOrder->status}'");
            }

            return "order #{$this->testOrder->id} at '{$this->testOrder->status}'";
        });

        $this->step('Record delivery and verify status transitions to partial', function () {
            $this->testOrder->items->first()->update(['delivered_quantity' => 4]);
            $this->testOrder->load('items');
            $this->testOrder->updateDeliveryStatus();
            $this->testOrder->save();

            if ($this->testOrder->status !== PurchaseOrder::STATUS_PARTIAL) {
                throw new \RuntimeException("expected '".PurchaseOrder::STATUS_PARTIAL."' after partial delivery, got '{$this->testOrder->status}'");
            }

            return "order #{$this->testOrder->id} now '{$this->testOrder->status}'";
        });

        $this->step('Complete delivery and verify status transitions to processed', function () use ($followUps) {
            $this->testOrder->items->first()->update(['delivered_quantity' => 10]);
            $this->testOrder->load('items');
            $this->testOrder->updateDeliveryStatus();
            $this->testOrder->save();

            if ($this->testOrder->status !== PurchaseOrder::STATUS_PROCESSED) {
                throw new \RuntimeException("expected '".PurchaseOrder::STATUS_PROCESSED."' after full delivery, got '{$this->testOrder->status}'");
            }

            // Exercises the same follow-up sync path real deliveries trigger --
            // does not send anything, only reads/writes order_follow_ups.
            $followUps->syncOrder($this->testOrder);

            return "order #{$this->testOrder->id} now '{$this->testOrder->status}'";
        });

        $this->step('Write an order audit entry via the real audit path', function () {
            OrderAudit::record($this->testOrder, 'smoke_test', 'Automated smoke test', Request::create('/'));

            $count = PurchaseOrderAudit::where('purchase_order_id', $this->testOrder->id)->count();
            if ($count < 1) {
                throw new \RuntimeException('OrderAudit::record did not create a row');
            }

            return "{$count} audit row(s) recorded";
        });
    }

    private function checkMessaging(string $runId): void
    {
        $this->step('Create a customer message on the real schema', function () use ($runId) {
            $message = CustomerMessage::create([
                'customer_id' => $this->testCustomer->id,
                'subject' => self::TAG.'Subject',
                'body' => self::TAG.'Body '.$runId,
                'sender_type' => 'company',
                'is_read' => true,
                'status' => 'open',
                'channel' => 'portal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($message->status !== 'open' || $message->channel !== 'portal') {
                throw new \RuntimeException('message did not persist expected default field values');
            }

            return "message #{$message->id} created";
        });
    }

    private function checkSearchTracking(string $runId): void
    {
        $this->step('Record and read back a search selection', function () use ($runId) {
            SearchSelection::create([
                'user_id' => $this->testStaff->id,
                'context' => SearchSelection::CONTEXT_CUSTOMER,
                'entity_key' => (string) $this->testCustomer->id,
                'label' => $runId.' Co',
            ]);

            $recent = SearchSelection::where('user_id', $this->testStaff->id)
                ->where('context', SearchSelection::CONTEXT_CUSTOMER)
                ->exists();

            if (! $recent) {
                throw new \RuntimeException('recorded selection was not found on read-back');
            }

            return 'recorded and read back successfully';
        });
    }

    /**
     * @param  callable(): string  $check
     */
    private function step(string $label, callable $check): void
    {
        $start = microtime(true);

        try {
            $detail = $check();
            $ms = round((microtime(true) - $start) * 1000);
            $this->results[] = ['step' => $label, 'ok' => true, 'detail' => "{$detail} ({$ms}ms)"];
        } catch (Throwable $e) {
            $this->results[] = ['step' => $label, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    private function cleanup(): void
    {
        // Deleted in dependency order. Each guarded independently so one
        // failure doesn't prevent the rest from being attempted.
        // PurchaseOrder is soft-deleted -- a plain delete() leaves the row
        // (with its customer_id FK) in place, which then blocks the
        // customer's own hard delete below. forceDelete() is required here.
        $this->safely(fn () => $this->testOrder?->items()->delete());
        $this->safely(fn () => $this->testOrder && PurchaseOrderAudit::where('purchase_order_id', $this->testOrder->id)->delete());
        $this->safely(fn () => $this->testOrder?->forceDelete());
        $this->safely(fn () => $this->testCustomer && CustomerMessage::where('customer_id', $this->testCustomer->id)->delete());
        $this->safely(fn () => $this->testCustomer?->delete());
        $this->safely(fn () => $this->testStaff && SearchSelection::where('user_id', $this->testStaff->id)->delete());
        $this->safely(fn () => $this->testStaff?->forceDelete());
    }

    /**
     * Sweeps up SMOKETEST- tagged rows regardless of which run created
     * them -- for recovering after a crashed run, not the normal path.
     */
    private function cleanupByTag(): void
    {
        $orders = PurchaseOrder::withTrashed()->where('po_number', 'like', self::TAG.'%')->get();
        foreach ($orders as $order) {
            PurchaseOrderAudit::where('purchase_order_id', $order->id)->delete();
            $order->items()->delete();
            $order->forceDelete();
        }

        Customer::where('company_name', 'like', self::TAG.'%')->delete();
        CustomerMessage::where('subject', self::TAG.'Subject')->delete();
        SearchSelection::where('label', 'like', '%SMOKETEST%')->delete();
        User::withTrashed()->where('email', 'like', '%@smoketest.invalid')->forceDelete();

        $this->info('Swept any leftover SMOKETEST- records.');
    }

    private function safely(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            // Cleanup continues past a single failed step (best-effort), but
            // the failure itself must stay visible -- a cleanup step that
            // fails silently is how leaked SMOKETEST- rows go unnoticed.
            $this->results[] = ['step' => 'cleanup', 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    private function report(): int
    {
        $rows = array_map(
            fn (array $r) => [$r['ok'] ? '✓' : '✗', $r['step'], $r['detail']],
            $this->results,
        );

        $this->table(['', 'Step', 'Detail'], $rows);

        $failed = count(array_filter($this->results, fn (array $r) => ! $r['ok']));
        $total = count($this->results);

        if ($failed > 0) {
            $this->error("{$failed}/{$total} checks failed.");

            return self::FAILURE;
        }

        $this->info("All {$total} checks passed. All test records cleaned up.");

        return self::SUCCESS;
    }
}
