<?php

namespace Tests\Feature\Reports;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

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
}
