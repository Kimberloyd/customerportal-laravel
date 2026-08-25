<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Builds the report without retaining every XLSX cell in PHP memory. Orders
 * arrive in database chunks, worksheet XML is written incrementally to disk,
 * and the completed archive is streamed to the client.
 */
class OrdersReportExport
{
    /**
     * @param  iterable<int, PurchaseOrder>  $orders
     */
    public static function stream(iterable $orders, array $filters, array $summary): StreamedResponse
    {
        $filename = 'orders-report-'.now()->format('Ymd').'.xlsx';

        return new StreamedResponse(function () use ($orders, $filters, $summary) {
            $worksheetPath = null;
            $sharedStringsPath = null;
            $archivePath = null;

            try {
                $worksheetPath = self::temporaryPath('orders-sheet-');
                $sharedStringsPath = self::temporaryPath('orders-strings-');
                $archivePath = self::temporaryPath('orders-xlsx-');
                self::writeWorksheet($worksheetPath, $sharedStringsPath, $orders, $filters, $summary);
                self::writeArchive($archivePath, $worksheetPath, $sharedStringsPath);
                self::streamFile($archivePath);
            } finally {
                foreach ([$worksheetPath, $sharedStringsPath, $archivePath] as $temporaryPath) {
                    if ($temporaryPath !== null) {
                        @unlink($temporaryPath);
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  iterable<int, PurchaseOrder>  $orders
     */
    private static function writeWorksheet(
        string $path,
        string $sharedStringsPath,
        iterable $orders,
        array $filters,
        array $summary,
    ): void {
        $stream = fopen($path, 'wb');
        $sharedStrings = fopen($sharedStringsPath, 'wb');
        if ($stream === false || $sharedStrings === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($sharedStrings)) {
                fclose($sharedStrings);
            }
            throw new RuntimeException('Unable to create the orders report worksheet.');
        }

        try {
            self::write($sharedStrings, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
            $sharedStringIndex = 0;
            self::write($stream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<cols>'
                .self::columnXml(1, 22).self::columnXml(2, 19).self::columnXml(3, 30)
                .self::columnXml(4, 45).self::columnXml(5, 16).self::columnXml(6, 17)
                .self::columnXml(7, 16).self::columnXml(8, 14).self::columnXml(9, 45)
                .'</cols><sheetData>');

            self::write($stream, '<row r="1">'.self::textCell(
                'A1', 'Customer Order Portal - Orders Report', 1, $sharedStrings, $sharedStringIndex
            ).'</row>');
            self::write($stream, '<row r="2">'.self::textCell(
                'A2', "Period: {$filters['period_label']}", 2, $sharedStrings, $sharedStringIndex
            ).'</row>');
            self::write($stream, '<row r="4">'
                .self::textCell('A4', 'Total Orders', 0, $sharedStrings, $sharedStringIndex).self::numberCell('B4', $summary['orders'])
                .self::textCell('C4', 'Ordered Units', 0, $sharedStrings, $sharedStringIndex).self::numberCell('D4', $summary['ordered_units'])
                .self::textCell('E4', 'Delivered Units', 0, $sharedStrings, $sharedStringIndex).self::numberCell('F4', $summary['delivered_units'])
                .self::textCell('G4', 'Balance Units', 0, $sharedStrings, $sharedStringIndex).self::numberCell('H4', $summary['balance_units'])
                .'</row>');

            $headers = [
                'PO Number', 'Date', 'Customer', 'Products',
                'Ordered Units', 'Delivered Units', 'Balance Units', 'Status', 'Remarks',
            ];
            self::write($stream, '<row r="6">'.self::cellsXml(
                6, $headers, [1, 2, 3, 4, 5, 6, 7, 8, 9], 3, $sharedStrings, $sharedStringIndex
            ).'</row>');

            $row = 7;
            foreach ($orders as $order) {
                $isPartial = in_array($order->status, PurchaseOrder::IN_PROGRESS_STATUSES, true);
                $statusLabel = $isPartial ? 'Partial' : ucfirst($order->status);
                $products = $order->items->map(fn ($item) => $item->display_name)->implode(', ') ?: '-';
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

                self::write($stream, '<row r="'.$row.'">'
                    .self::cellsXml(
                        $row, $values, [1, 2, 3, 4, 8, 9], 4, $sharedStrings, $sharedStringIndex
                    )
                    .'</row>');
                $row++;
            }

            $lastRow = max($row - 1, 6);
            self::write($stream, '</sheetData>'
                .'<mergeCells count="2"><mergeCell ref="A1:I1"/><mergeCell ref="A2:I2"/></mergeCells>'
                .'<autoFilter ref="A6:I'.$lastRow.'"/>'
                .'</worksheet>');
            self::write($sharedStrings, '</sst>');
        } finally {
            fclose($stream);
            fclose($sharedStrings);
        }
    }

    /** @param resource $sharedStrings */
    private static function cellsXml(
        int $row,
        array $values,
        array $textColumns,
        int $style,
        $sharedStrings,
        int &$sharedStringIndex,
    ): string {
        $xml = '';
        foreach ($values as $index => $value) {
            $column = $index + 1;
            $coordinate = chr(64 + $column).$row;
            $xml .= in_array($column, $textColumns, true)
                ? self::textCell($coordinate, (string) $value, $style, $sharedStrings, $sharedStringIndex)
                : self::numberCell($coordinate, (int) $value, $style);
        }

        return $xml;
    }

    /** @param resource $sharedStrings */
    private static function textCell(
        string $coordinate,
        string $value,
        int $style,
        $sharedStrings,
        int &$sharedStringIndex,
    ): string {
        self::write($sharedStrings, '<si><t xml:space="preserve">'.self::escape($value).'</t></si>');

        return '<c r="'.$coordinate.'" s="'.$style.'" t="s"><v>'.$sharedStringIndex++.'</v></c>';
    }

    private static function numberCell(string $coordinate, int $value, int $style = 0): string
    {
        return '<c r="'.$coordinate.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
    }

    private static function columnXml(int $column, int $width): string
    {
        return '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
    }

    private static function writeArchive(
        string $archivePath,
        string $worksheetPath,
        string $sharedStringsPath,
    ): void {
        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the orders report archive.');
        }

        try {
            self::addArchiveString($archive, '[Content_Types].xml', self::contentTypesXml());
            self::addArchiveString($archive, '_rels/.rels', self::rootRelationshipsXml());
            self::addArchiveString($archive, 'xl/workbook.xml', self::workbookXml());
            self::addArchiveString($archive, 'xl/_rels/workbook.xml.rels', self::workbookRelationshipsXml());
            self::addArchiveString($archive, 'xl/styles.xml', self::stylesXml());
            if (! $archive->addFile($worksheetPath, 'xl/worksheets/sheet1.xml')) {
                throw new RuntimeException('Unable to add the worksheet to the orders report archive.');
            }
            if (! $archive->addFile($sharedStringsPath, 'xl/sharedStrings.xml')) {
                throw new RuntimeException('Unable to add shared strings to the orders report archive.');
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('Unable to finish the orders report archive.');
            }
        }
    }

    private static function addArchiveString(ZipArchive $archive, string $path, string $contents): void
    {
        if (! $archive->addFromString($path, $contents)) {
            throw new RuntimeException("Unable to add {$path} to the orders report archive.");
        }
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
    }

    private static function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Orders Report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE9EEF5"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private static function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary orders report file.');
        }

        return $path;
    }

    /** @param resource $stream */
    private static function write($stream, string $contents): void
    {
        $length = strlen($contents);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the orders report worksheet.');
            }
            $offset += $written;
        }
    }

    private static function streamFile(string $path): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read the completed orders report.');
        }

        try {
            if (fpassthru($stream) === false) {
                throw new RuntimeException('Unable to stream the completed orders report.');
            }
        } finally {
            fclose($stream);
        }
    }

    private static function escape(string $value): string
    {
        $value = StringHelper::sanitizeUTF8($value);
        $value = StringHelper::controlCharacterPHP2OOXML($value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
