<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Fluent;

trait CreatesOrderFixtures
{
    /**
     * Products live in the inventoryapp API rather than this database (see
     * the 2026_08_18 migration that dropped the local `products` table), so
     * there is nothing to persist here. This returns a plain value object
     * standing in for one catalogue row -- enough for fixtures that only
     * need an id and the snapshot fields copied onto an order item.
     *
     * Tests that exercise order *creation* need the API itself faked, since
     * PurchaseOrderController resolves products through InventoryApiClient.
     */
    protected function makeProduct(string $name = 'Widget', array $overrides = []): Fluent
    {
        static $nextId = 1;

        return new Fluent(array_merge([
            'id' => $nextId++,
            'product_name' => $name,
            'sku' => null,
            'unit' => null,
            'description' => null,
            'unit_price' => 0,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeCustomer(string $name = 'Acme Co', ?User $user = null): Customer
    {
        return Customer::create([
            'company_name' => $name,
            'is_active' => true,
            'user_id' => $user?->id,
        ]);
    }

    protected function makeOrder(Customer $customer, string $status, \DateTimeInterface|string $submittedAt, array $items = []): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'status' => $status,
            'submitted_at' => $submittedAt,
        ]);

        foreach ($items as $item) {
            PurchaseOrderItem::create(array_merge([
                'purchase_order_id' => $order->id,
            ], $item));
        }

        return $order;
    }

    protected function makeThread(?Customer $customer = null, array $overrides = []): CustomerMessage
    {
        $now = now();

        return CustomerMessage::create(array_merge([
            'customer_id' => $customer?->id,
            'subject' => 'Test Subject',
            'body' => 'Test body',
            'sender_type' => 'company',
            'is_read' => false,
            'status' => 'open',
            'channel' => 'portal',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }
}
