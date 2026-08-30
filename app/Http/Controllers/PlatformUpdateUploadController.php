<?php

namespace App\Http\Controllers;

use App\Services\PlatformUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles the update ZIP upload as a plain form POST rather than through
 * Livewire's WithFileUploads — an update package can legitimately be tens
 * of MB (it carries the full application, vendor/ included, matching the
 * installer's own no-composer-at-runtime philosophy), well past Livewire's
 * own 12MB temporary-upload ceiling. A plain POST is bound only by PHP's
 * own upload_max_filesize/post_max_size, which a host can raise without
 * touching any Livewire-wide config that every other upload in the app
 * also shares.
 */
class PlatformUpdateUploadController extends Controller
{
    public function __invoke(Request $request, PlatformUpdateService $updateService): RedirectResponse
    {
        $request->validate([
            'update_zip' => ['required', 'file', 'mimes:zip', 'max:'.(300 * 1024)],
        ]);

        $storedPath = storage_path('app/private/update-uploads/'.Str::uuid().'.zip');
        File::ensureDirectoryExists(dirname($storedPath));
        $request->file('update_zip')->move(dirname($storedPath), basename($storedPath));

        try {
            $manifest = $updateService->readManifest($storedPath);
            $updateService->validateManifest($manifest);
        } catch (Throwable $e) {
            File::delete($storedPath);

            return redirect()->route('admin.updates.index')->with('update_error', $e->getMessage());
        }

        $request->session()->put('pending_update', [
            'path' => $storedPath,
            'manifest' => $manifest,
        ]);

        return redirect()->route('admin.updates.index');
    }
}
