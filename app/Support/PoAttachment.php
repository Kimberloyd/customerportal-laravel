<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ports save_po_attachment()/delete_po_attachment() from
 * app/purchase_orders/purchase_order_routes.py. Files are stored on
 * the "local" disk (storage/app/private -- not the public disk, never
 * web-reachable directly) and only ever served back through an
 * authenticated route that repeats the same access check as the order
 * itself (see PurchaseOrderController::attachment()).
 */
class PoAttachment
{
    private const DIRECTORY = 'purchase_order_attachments';

    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];

    /**
     * Magic-byte signatures for the extensions above -- the extension is
     * only a label the uploader chose; this confirms the bytes actually
     * match it, so a renamed file can't slip past validation.
     */
    private const SIGNATURES = [
        'pdf' => ["%PDF-"],
        'png' => ["\x89PNG\r\n\x1a\n"],
        'jpg' => ["\xff\xd8\xff"],
        'jpeg' => ["\xff\xd8\xff"],
    ];

    /**
     * @throws \InvalidArgumentException on invalid extension/content
     */
    public static function save(UploadedFile $file, string $poNumber): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Attachment must be a PDF, PNG, JPG, or JPEG file.');
        }

        if (! self::contentMatchesExtension($file, $extension)) {
            throw new \InvalidArgumentException('Attachment content does not match a PDF, PNG, JPG, or JPEG file.');
        }

        $safePoNumber = Str::slug($poNumber ?: 'order', '_') ?: 'order';
        $filename = sprintf('%s_%s.%s', $safePoNumber, bin2hex(random_bytes(16)), $extension);

        Storage::disk('local')->putFileAs(self::DIRECTORY, $file, $filename);

        return $filename;
    }

    public static function delete(?string $storedName): void
    {
        if (! $storedName) {
            return;
        }

        // Reject anything that could climb out of the attachments
        // directory (e.g. a stored name containing "../") before ever
        // touching the filesystem.
        if (str_contains($storedName, '..') || str_contains($storedName, '/') || str_contains($storedName, '\\')) {
            return;
        }

        Storage::disk('local')->delete(self::DIRECTORY.'/'.$storedName);
    }

    public static function path(string $storedName): string
    {
        return self::DIRECTORY.'/'.$storedName;
    }

    private static function contentMatchesExtension(UploadedFile $file, string $extension): bool
    {
        $signatures = self::SIGNATURES[$extension] ?? [];
        if (! $signatures) {
            return false;
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 16);
        fclose($handle);

        foreach ($signatures as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }
}
