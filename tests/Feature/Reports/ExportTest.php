<?php

namespace Tests\Feature\Reports;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_response_has_correct_content_type_and_filename(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->get('/reports/orders/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'orders-report-'.now()->format('Ymd').'.xlsx',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_formula_injection_hardened_columns_stay_text_even_with_leading_equals(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('=2+2 Evil Co');
        $product = $this->makeProduct();
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'product_name' => '=SUM(A1:A10)'],
        ])->update(['remarks' => '=cmd|/c calc']);

        $response = $this->actingAsUser($staff)->get('/reports/orders/export');

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmpFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmpFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Row 7 is the first data row (title, period, blank, summary,
        // blank, headers, then data). Columns C (customer), D (products),
        // I (remarks) must be stored as literal text, not formulas.
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C7')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D7')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('I7')->getDataType());
        $this->assertStringContainsString('Evil Co', (string) $sheet->getCell('C7')->getValue());
        $this->assertStringContainsString('cmd', (string) $sheet->getCell('I7')->getValue());

        unlink($tmpFile);
    }

    public function test_export_respects_same_filters_as_the_page(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'delivered_quantity' => 1],
        ]);
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/orders/export?status=completed');

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmpFile, $response->streamedContent());
        $sheet = IOFactory::load($tmpFile)->getActiveSheet();

        // Summary row (row 4): "Total Orders" label in A4, count in B4.
        $this->assertEquals(1, $sheet->getCell('B4')->getValue());
        unlink($tmpFile);
    }

    public function test_export_streams_orders_from_a_bounded_query(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_name' => 'Measured Product', 'quantity' => 1],
        ]);
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $response = $this->actingAsUser($staff)->get('/reports/orders/export');
        $response->streamedContent();

        $this->assertTrue(collect($statements)->contains(
            fn (string $sql) => str_contains($sql, 'from "purchase_orders"')
                && str_contains($sql, 'limit 250')
                && ! str_contains($sql, 'select *')
        ));
    }

    public function test_export_round_trips_xml_special_and_control_characters(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('A & B <Care>');
        $productName = "Control \x01 Product _x0001_";
        $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_name' => $productName, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->get('/reports/orders/export');
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmpFile, $response->streamedContent());
        $sheet = IOFactory::load($tmpFile)->getActiveSheet();

        $this->assertSame('A & B <Care>', $sheet->getCell('C7')->getValue());
        $this->assertSame($productName, $sheet->getCell('D7')->getValue());
        $this->assertSame('A6:I7', $sheet->getAutoFilter()->getRange());
        $this->assertSame('A7', $sheet->getFreezePane());
        unlink($tmpFile);
    }
}
