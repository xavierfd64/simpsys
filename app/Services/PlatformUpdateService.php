<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Self-service platform update: validates an uploaded "BizManager update
 * package" ZIP, backs up whatever it's about to replace, applies the new
 * files, runs migrations, and rolls back on any failure. Built for shared
 * hosting specifically — no shell access assumed, no atomic symlink swap
 * available, so backups use a fast same-filesystem rename/move rather
 * than a slow copy, and rollback is file-by-file rather than a single
 * directory swap.
 *
 * Package format (see docs/UPDATE_PACKAGE_FORMAT.md):
 *   manifest.json         { type: "bizmanager-update", version, min_from_version?, release_notes? }
 *   files/...              mirrors the application's own directory structure
 */
class PlatformUpdateService
{
    /**
     * Paths (relative to the app root) an update package is never allowed
     * to touch, regardless of what it contains — user data, the local
     * environment config, and framework-generated cache.
     */
    protected const PROTECTED_PREFIXES = [
        '.env',
        'storage/',
        'public/storage',
        'bootstrap/cache/',
        'database/database.sqlite',
    ];

    public function __construct(protected ?string $basePath = null)
    {
        $this->basePath ??= base_path();
    }

    public function currentVersion(): string
    {
        $path = $this->basePath.'/VERSION';

        return File::exists($path) ? trim(File::get($path)) : '0.0.0';
    }

    /**
     * Reads and validates just the manifest from the package without
     * extracting anything else — cheap enough to call before the admin
     * commits to a full install, so they can review what they're about to
     * apply first.
     *
     * @return array{type: string, version: string, min_from_version?: string, release_notes?: string}
     */
    public function readManifest(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('This file is not a valid ZIP archive.');
        }

        $raw = $zip->getFromName('manifest.json');
        $zip->close();

        if ($raw === false) {
            throw new RuntimeException('This ZIP does not contain a manifest.json — it is not a recognized update package.');
        }

        $manifest = json_decode($raw, true);

        if (! is_array($manifest) || ($manifest['type'] ?? null) !== 'bizmanager-update') {
            throw new RuntimeException('This ZIP is not a valid BizManager update package.');
        }

        if (empty($manifest['version']) || ! is_string($manifest['version'])) {
            throw new RuntimeException('The update package manifest is missing a version number.');
        }

        return $manifest;
    }

    /**
     * @param  array{version: string, min_from_version?: string}  $manifest
     */
    public function validateManifest(array $manifest): void
    {
        $current = $this->currentVersion();

        if (version_compare($manifest['version'], $current, '<=')) {
            throw new RuntimeException("This package (version {$manifest['version']}) is not newer than the currently installed version ({$current}).");
        }

        if (! empty($manifest['min_from_version']) && version_compare($current, $manifest['min_from_version'], '<')) {
            throw new RuntimeException("This update requires version {$manifest['min_from_version']} or later — the currently installed version is {$current}. Install the intermediate update first.");
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function install(string $zipPath): array
    {
        $manifest = $this->readManifest($zipPath);
        $this->validateManifest($manifest);
        $this->assertNoUnsafePaths($zipPath);

        $tempDir = $this->basePath.'/storage/app/update-temp/'.Str::uuid();
        File::ensureDirectoryExists($tempDir);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            File::deleteDirectory($tempDir);
            throw new RuntimeException('Could not open the update package for extraction.');
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $sourceFilesDir = $tempDir.'/files';

        if (! File::isDirectory($sourceFilesDir)) {
            File::deleteDirectory($tempDir);
            throw new RuntimeException('The update package is missing its files/ directory.');
        }

        $backupDir = $this->basePath.'/storage/app/update-backups/'.now()->format('Y-m-d_His');
        File::ensureDirectoryExists($backupDir);

        $applied = [];

        try {
            $this->applyFiles($sourceFilesDir, $backupDir, $applied);

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            File::put($this->basePath.'/VERSION', $manifest['version']."\n");
            File::deleteDirectory($tempDir);

            return [
                'success' => true,
                'message' => "Successfully updated to version {$manifest['version']}.",
            ];
        } catch (Throwable $e) {
            $this->rollback($applied, $backupDir);
            File::deleteDirectory($tempDir);
            report($e);

            return [
                'success' => false,
                'message' => 'The update failed and file changes were rolled back: '.$e->getMessage()
                    .' If any database migrations ran before the failure, they were not automatically reverted — check your database before retrying.',
            ];
        }
    }

    /**
     * Rejects the whole package before extracting anything if any entry
     * name tries to escape the extraction directory — defense in depth on
     * top of ZipArchive::extractTo()'s own protection against this.
     */
    protected function assertNoUnsafePaths(string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the update package.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $name)) {
                $zip->close();

                throw new RuntimeException('The update package contains an unsafe file path and was rejected.');
            }
        }

        $zip->close();
    }

    /**
     * Copies every file under $source into the app root, backing up
     * (moving, not copying — fast on shared hosting) whatever already
     * exists at each destination first, and skipping anything under a
     * protected path or an already-shipped migration file.
     *
     * @param  array<int, array{relative: string, had_backup: bool}>  $applied
     */
    protected function applyFiles(string $source, string $backupDir, array &$applied): void
    {
        foreach (File::allFiles($source) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if ($this->isProtectedPath($relative)) {
                continue;
            }

            if (str_starts_with($relative, 'database/migrations/') && File::exists($this->basePath.'/'.$relative)) {
                // Never overwrite a migration that may have already run —
                // fixes belong in a new migration file, not an edit to one
                // already shipped.
                continue;
            }

            $destPath = $this->basePath.'/'.$relative;
            File::ensureDirectoryExists(dirname($destPath));

            if (File::exists($destPath)) {
                $backupPath = $backupDir.'/'.$relative;
                File::ensureDirectoryExists(dirname($backupPath));
                File::move($destPath, $backupPath);
                $applied[] = ['relative' => $relative, 'had_backup' => true];
            } else {
                $applied[] = ['relative' => $relative, 'had_backup' => false];
            }

            File::copy($file->getPathname(), $destPath);
        }
    }

    protected function isProtectedPath(string $relative): bool
    {
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{relative: string, had_backup: bool}>  $applied
     */
    protected function rollback(array $applied, string $backupDir): void
    {
        foreach (array_reverse($applied) as $entry) {
            $destPath = $this->basePath.'/'.$entry['relative'];

            if (File::exists($destPath)) {
                File::delete($destPath);
            }

            if ($entry['had_backup']) {
                $backupPath = $backupDir.'/'.$entry['relative'];

                if (File::exists($backupPath)) {
                    File::ensureDirectoryExists(dirname($destPath));
                    File::move($backupPath, $destPath);
                }
            }
        }
    }
}
