<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ports the XLSX-building half of app/reports/report_routes.py's
 * order_report_export(). The formula-injection hardening (CWE-1236)
 * on the customer/products/remarks columns is the security-critical
 * piece here -- ported exactly, not approximated.
 */
class OrdersReportExport
{
    /**
     * Columns containing customer- or staff-entered free text -- a
     * string cell starting with =, +, -, or @ would otherwise be read
     * as a formula by Excel/LibreOffice/Sheets when opened.
     */
    private const TEXT_HARDENED_COLUMNS = [3, 4, 9];

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    public static function stream(Collection $orders, array $filters, array $summary): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Orders Report');

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'Customer Order Portal - Orders Report');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', "Period: {$filters['period_label']}");
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 3 blank, row 4 summary, row 5 blank, row 6 headers, data from row 7.
        $sheet->fromArray([
            'Total Orders', $summary['orders'],
            'Ordered Units', $summary['ordered_units'],
            'Delivered Units', $summary['delivered_units'],
            'Balance Units', $summary['balance_units'],
        ], null, 'A4');

        $headers = [
            'PO Number', 'Date', 'Customer', 'Products',
            'Ordered Units', 'Delivered Units', 'Balance Units', 'Status', 'Remarks',
        ];
        $sheet->fromArray($headers, null, 'A6');
        $sheet->getStyle('A6:I6')->getFont()->setBold(true);
        $sheet->getStyle('A6:I6')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E9EEF5');

        $row = 7;
        foreach ($orders as $order) {
            $isPartial = in_array($order->status, PurchaseOrder::IN_PROGRESS_STATUSES, true);
            $statusLabel = $isPartial ? 'Partial' : ucfirst($order->status);
            $products = $order->items->map(fn ($i) => $i->display_name)->implode(', ') ?: '-';

            $values = [
                $order->po_number,
                $order->submitted_at?->format('Y-m-d H:i') ?? '',
                $order->customer?->company_name ?? '',
                $products,
                (int) $order->items->sum('quantity'),
                (int) $order->items->sum('delivered_quantity'),
                $order->balance_units,
                $statusLabel,
                $order->remarks ?? '',
            ];

            foreach ($values as $col => $value) {
                $cellCol = chr(65 + $col);
                $columnNumber = $col + 1;
                if (in_array($columnNumber, self::TEXT_HARDENED_COLUMNS, true)) {
                    $sheet->setCellValueExplicit("{$cellCol}{$row}", (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("{$cellCol}{$row}", $value);
                }
            }
            $row++;
        }

        $widths = [22, 19, 30, 45, 16, 17, 16, 14, 45];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $lastRow = max($row - 1, 6);
        $sheet->freezePane('A7');
        $sheet->setAutoFilter("A6:I{$lastRow}");
        $sheet->getStyle("A7:I{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        $writer = new Xlsx($spreadsheet);
        $filename = 'orders-report-'.now()->format('Ymd').'.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
