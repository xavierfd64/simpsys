<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores tenant-uploaded images (product/supply photos, logos, receipts)
 * under tenants/{tenant_uuid}/{folder}/ on the public disk, using a
 * generated filename — the original filename is never trusted as a
 * storage path.
 */
class TenantStorage
{
    public const MAX_KILOBYTES = 5 * 1024;

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function storeImage(UploadedFile $file, string $folder, Tenant $tenant): string
    {
        $filename = Str::uuid().'.'.static::safeExtension($file);
        $path = "tenants/{$tenant->uuid}/{$folder}";

        return $file->storeAs($path, $filename, 'public');
    }

    /**
     * Same pattern as storeImage(), for platform-wide (non-tenant) uploads
     * like platform branding — stored under platform/{folder}/ instead of
     * a per-tenant path.
     */
    public static function storePlatformImage(UploadedFile $file, string $folder): string
    {
        $filename = Str::uuid().'.'.static::safeExtension($file);

        return $file->storeAs("platform/{$folder}", $filename, 'public');
    }

    /**
     * The stored file's extension must never come from the client-supplied
     * original filename — every caller here validates the upload with
     * mimes:jpg,jpeg,png,webp(,ico) first, and Laravel's own validator
     * already blocks the most common php/php3-8/phtml/phar client
     * extensions, but that blocklist doesn't cover every extension a given
     * shared host might be configured to execute as a script (.pht and
     * .phtm are common on cPanel-style Apache configs, which is exactly
     * what this app targets). A real image whose client-side filename
     * claims one of those would still pass mimes validation on content
     * alone and previously got stored with that literal claimed extension
     * via getClientOriginalExtension() — the classic image-polyglot
     * upload-to-RCE vector. Deriving the extension from the file's own
     * detected content type instead closes this regardless of what
     * extension the client claims; an unrecognized/unexpected detected
     * type (which validation should already have rejected) falls back to
     * the inert ".bin", never anything a web server could execute.
     */
    protected static function safeExtension(UploadedFile $file): string
    {
        $map = [
            'jpg' => 'jpg', 'jpeg' => 'jpg', 'jpe' => 'jpg', 'jfif' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            'bmp' => 'bmp',
            'ico' => 'ico', 'vnd.microsoft.icon' => 'ico',
        ];

        $detected = strtolower((string) $file->guessExtension());

        return $map[$detected] ?? 'bin';
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
