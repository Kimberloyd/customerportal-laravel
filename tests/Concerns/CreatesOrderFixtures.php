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
     * Raw API-shaped rows handed back by the faked catalogue endpoint,
     * accumulated across makeProduct() calls within a single test.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $fakeCatalogue = [];

    /**
     * Products live in the inventoryapp API rather than this database (see
     * the 2026_08_18 migration that dropped the local `products` table), so
     * there is nothing to persist. This returns a value object standing in
     * for one catalogue row *and* registers it with the faked API, so the
     * controllers that resolve products over HTTP can find it.
     */
    protected function makeProduct(string $name = 'Widget', array $overrides = []): Fluent
    {
        $attributes = array_merge([
            'id' => count($this->fakeCatalogue) + 1,
            'product_name' => $name,
            'sku' => null,
            'category' => 'OTHERS',
            'generic_name' => null,
            'description' => null,
            'dosage' => null,
            'unit' => null,
            'unit_price' => 0,
            'is_active' => true,
        ], $overrides);

        // InventoryApiClient::mapProduct() reads the upstream field names, so
        // the faked payload has to speak the API's vocabulary (name, generic,
        // unit_type, current_price) rather than this app's.
        $this->fakeCatalogue[] = [
            'id' => $attributes['id'],
            'sku' => $attributes['sku'],
            'name' => $attributes['product_name'],
            'category' => $attributes['category'],
            'generic' => $attributes['generic_name'],
            'description' => $attributes['description'],
            'dosage' => $attributes['dosage'],
            'unit_type' => $attributes['unit'],
            'current_price' => $attributes['unit_price'],
            'is_active' => $attributes['is_active'],
        ];

        $this->fakeInventoryApi($this->fakeCatalogue);

        return new Fluent($attributes);
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
            ], $this->snapshotItem($item)));
        }

        return $order;
    }

    /**
     * Order items no longer carry a product_id -- they store a snapshot of
     * the product taken at order time. Fixtures still describe a line by the
     * id makeProduct() handed back, so translate that into the snapshot the
     * real controller would have written, unless the test set one itself.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function snapshotItem(array $item): array
    {
        $productId = $item['product_id'] ?? null;
        unset($item['product_id']);

        if ($productId === null || array_key_exists('product_name', $item)) {
            return $item;
        }

        foreach ($this->fakeCatalogue as $row) {
            if ($row['id'] === $productId) {
                return $item + [
                    'product_name' => $row['name'],
                    'sku' => $row['sku'],
                    'unit' => $row['unit_type'],
                    'description' => $row['description'],
                    'unit_price' => $row['current_price'],
                ];
            }
        }

        return $item;
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
