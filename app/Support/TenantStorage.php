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
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
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
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs("platform/{$folder}", $filename, 'public');
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
