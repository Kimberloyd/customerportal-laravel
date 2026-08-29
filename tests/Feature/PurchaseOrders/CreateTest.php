<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_orders_page_only_loads_the_product_catalog_when_the_modal_requests_it(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeCustomer();
        $this->makeProduct('Deferred Product');

        $response = $this->actingAsUser($staff)->get('/orders');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Index')
            ->has('createOrderCustomers', 1)
            ->missing('createOrderProducts'));

        $catalogResponse = $this->actingAsUser($staff)->get('/orders', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            'X-Inertia-Partial-Component' => 'PurchaseOrders/Index',
            'X-Inertia-Partial-Data' => 'createOrderProducts',
        ]);

        $catalogResponse->assertOk()
            ->assertJsonPath('component', 'PurchaseOrders/Index')
            ->assertJsonCount(1, 'props.createOrderProducts')
            ->assertJsonPath('props.createOrderProducts.0.product_name', 'Deferred Product');
    }

    public function test_create_url_redirects_to_the_orders_modal(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)
            ->get('/orders/create')
            ->assertRedirect(route('purchase-orders.index', ['create' => 1]));
    }

    public function test_creates_order_with_a_resolved_product_id_line(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Amoxicillin 500mg', ['unit_price' => 12.50]);

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'remarks' => 'Rush order',
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [3],
        ]);

        $response->assertRedirect(route('purchase-orders.index'));
        $order = PurchaseOrder::first();
        $this->assertNotNull($order);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('Rush order', $order->remarks);
        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $order->status);

        $item = PurchaseOrderItem::first();
        $this->assertSame(3, $item->quantity);
        $this->assertSame('Amoxicillin 500mg', $item->product_name);
        $this->assertEquals(12.50, $item->unit_price);
        $this->assertEquals(37.50, $item->line_total);
    }

    public function test_resolves_line_via_unambiguous_product_search(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $this->makeProduct('Paracetamol 500mg', ['sku' => 'PARA-500']);

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [''],
            'product_search' => ['paracetamol'],
            'quantity' => [2],
        ]);

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertSame('Paracetamol 500mg', PurchaseOrderItem::first()->product_name);
    }

    public function test_rejects_ambiguous_product_search(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $this->makeProduct('Amoxicillin 500mg Capsule');
        $this->makeProduct('Amoxicillin 250mg Capsule');

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [''],
            'product_search' => ['amoxicillin'],
            'quantity' => [1],
        ]);

        $response->assertSessionHasErrors('items.0');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_rejects_unmatched_product_search(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [''],
            'product_search' => ['nonexistent-drug'],
            'quantity' => [1],
        ]);

        $response->assertSessionHasErrors('items.0');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_rejects_quantity_less_than_one(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [0],
        ]);

        $response->assertSessionHasErrors('items.0');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_rejects_zero_resolved_lines(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [''],
            'product_search' => [''],
            'quantity' => [''],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_customer_role_cannot_override_customer_id(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();

        $this->actingAsUser($user)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $other->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $order = PurchaseOrder::first();
        $this->assertSame($own->id, $order->customer_id);
    }

    public function test_snapshots_product_fields_onto_the_item(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Original Name', ['sku' => 'ORIG-1']);

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $product->update(['product_name' => 'Renamed Later']);

        $item = PurchaseOrderItem::first();
        $this->assertSame('Original Name', $item->product_name);
    }

    public function test_writes_exactly_one_audit_row_with_order_created_action(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $this->assertSame(1, PurchaseOrderAudit::count());
        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Order Created', $audit->action);
        $this->assertSame('Created with 1 product line(s).', $audit->details);
        $this->assertSame($staff->id, $audit->actor_user_id);
    }

    public function test_accepts_a_valid_pdf_attachment(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $file = UploadedFile::fake()->createWithContent('order.pdf', "%PDF-1.4\n%fake pdf content for testing");

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
            'po_attachment' => $file,
        ]);

        $response->assertRedirect(route('purchase-orders.index'));
        $order = PurchaseOrder::first();
        $this->assertNotNull($order->po_file);
        Storage::disk('local')->assertExists('purchase_order_attachments/'.$order->po_file);
    }

    public function test_rejects_a_file_whose_content_does_not_match_its_extension(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $file = UploadedFile::fake()->createWithContent('order.pdf', 'just plain text, not a real pdf');

        $response = $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
            'po_attachment' => $file,
        ]);

        $response->assertSessionHasErrors('po_attachment');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_orphaned_customer_gets_403_on_get_and_post(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/orders/create')->assertStatus(403);
        $this->actingAsUser($user)->post('/orders', [])->assertStatus(403);
    }
}
