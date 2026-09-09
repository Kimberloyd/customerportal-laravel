<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductReturnAttachment
{
    private const DIRECTORY = 'product_return_attachments';

    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    private const SIGNATURES = [
        'png' => ["\x89PNG\r\n\x1a\n"],
        'jpg' => ["\xff\xd8\xff"],
        'jpeg' => ["\xff\xd8\xff"],
        'webp' => ['RIFF'],
    ];

    /**
     * @throws \InvalidArgumentException when the extension or file content is invalid
     * @throws \RuntimeException when the file cannot be stored
     */
    public static function save(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Choose a JPG, PNG, or WebP image.');
        }

        if (! self::contentMatchesExtension($file, $extension)) {
            throw new \InvalidArgumentException('The selected file is not a valid JPG, PNG, or WebP image.');
        }

        $filename = bin2hex(random_bytes(24)).'.'.$extension;
        $stored = Storage::disk('local')->putFileAs(self::DIRECTORY, $file, $filename);

        if ($stored === false) {
            throw new \RuntimeException('The return image could not be saved. Try again.');
        }

        return $filename;
    }

    public static function delete(?string $storedName): void
    {
        if (! self::isSafeStoredName($storedName)) {
            return;
        }

        Storage::disk('local')->delete(self::path($storedName));
    }

    /** @param array<int, string> $storedNames */
    public static function deleteMany(array $storedNames): void
    {
        foreach ($storedNames as $storedName) {
            self::delete($storedName);
        }
    }

    public static function path(string $storedName): string
    {
        return self::DIRECTORY.'/'.$storedName;
    }

    public static function isSafeStoredName(?string $storedName): bool
    {
        return $storedName !== null
            && $storedName !== ''
            && ! str_contains($storedName, '..')
            && ! str_contains($storedName, '/')
            && ! str_contains($storedName, '\\');
    }

    private static function contentMatchesExtension(UploadedFile $file, string $extension): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        $matchesSignature = collect(self::SIGNATURES[$extension] ?? [])
            ->contains(fn (string $signature) => str_starts_with($header, $signature));

        if (! $matchesSignature) {
            return false;
        }

        if ($extension === 'webp') {
            return substr($header, 8, 4) === 'WEBP';
        }

        return true;
    }
}
