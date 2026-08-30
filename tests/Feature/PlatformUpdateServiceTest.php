<?php

namespace Tests\Feature;

use App\Services\PlatformUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class PlatformUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway "app root" so this test can never touch the real
        // project files — every install()/rollback() call under test
        // operates entirely inside this directory.
        $this->sandbox = storage_path('framework/testing/update-sandbox/'.Str::uuid());
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    protected function service(): PlatformUpdateService
    {
        return new PlatformUpdateService($this->sandbox);
    }

    /**
     * @param  array<string, string>  $files  relative-path => contents, written under files/
     */
    protected function buildPackage(array $manifest, array $files = []): string
    {
        $zipPath = storage_path('framework/testing/update-sandbox/'.Str::uuid().'.zip');
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode($manifest));

        foreach ($files as $relative => $contents) {
            $zip->addFromString('files/'.$relative, $contents);
        }

        $zip->close();

        return $zipPath;
    }

    public function test_current_version_reads_the_version_file(): void
    {
        File::put($this->sandbox.'/VERSION', "1.2.3\n");

        $this->assertSame('1.2.3', $this->service()->currentVersion());
    }

    public function test_current_version_defaults_when_no_version_file_exists(): void
    {
        $this->assertSame('0.0.0', $this->service()->currentVersion());
    }

    public function test_read_manifest_rejects_a_non_zip_file(): void
    {
        $path = storage_path('framework/testing/update-sandbox/'.Str::uuid().'.zip');
        File::put($path, 'not a zip file');

        $this->expectExceptionMessage('not a valid ZIP archive');
        $this->service()->readManifest($path);
    }

    public function test_read_manifest_rejects_a_zip_with_no_manifest(): void
    {
        $zipPath = storage_path('framework/testing/update-sandbox/'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('files/app/Foo.php', '<?php');
        $zip->close();

        $this->expectExceptionMessage('does not contain a manifest.json');
        $this->service()->readManifest($zipPath);
    }

    public function test_read_manifest_rejects_the_wrong_package_type(): void
    {
        $zipPath = $this->buildPackage(['type' => 'something-else', 'version' => '2.0.0']);

        $this->expectExceptionMessage('not a valid BizManager update package');
        $this->service()->readManifest($zipPath);
    }

    public function test_validate_manifest_rejects_a_version_that_is_not_newer(): void
    {
        File::put($this->sandbox.'/VERSION', "2.0.0\n");
        $service = $this->service();

        $this->expectExceptionMessage('is not newer than');
        $service->validateManifest(['type' => 'bizmanager-update', 'version' => '1.9.0']);
    }

    public function test_validate_manifest_rejects_when_minimum_version_is_not_met(): void
    {
        File::put($this->sandbox.'/VERSION', "1.0.0\n");
        $service = $this->service();

        $this->expectExceptionMessage('requires version 1.5.0 or later');
        $service->validateManifest(['type' => 'bizmanager-update', 'version' => '2.0.0', 'min_from_version' => '1.5.0']);
    }

    public function test_install_applies_new_files_and_backs_up_existing_ones(): void
    {
        File::put($this->sandbox.'/VERSION', "1.0.0\n");
        File::ensureDirectoryExists($this->sandbox.'/app');
        File::put($this->sandbox.'/app/Existing.php', 'old contents');

        $zipPath = $this->buildPackage(
            ['type' => 'bizmanager-update', 'version' => '1.1.0', 'release_notes' => 'Test release'],
            [
                'app/Existing.php' => 'new contents',
                'app/BrandNew.php' => 'brand new file',
            ],
        );

        $result = $this->service()->install($zipPath);

        $this->assertTrue($result['success']);
        $this->assertSame('new contents', File::get($this->sandbox.'/app/Existing.php'));
        $this->assertSame('brand new file', File::get($this->sandbox.'/app/BrandNew.php'));
        $this->assertSame('1.1.0', trim(File::get($this->sandbox.'/VERSION')));

        // The old version of the replaced file must exist somewhere under
        // a backup directory rather than being discarded.
        $backups = File::directories($this->sandbox.'/storage/app/update-backups');
        $this->assertNotEmpty($backups);
        $this->assertSame('old contents', File::get($backups[0].'/app/Existing.php'));
    }

    public function test_install_never_writes_to_protected_paths(): void
    {
        File::put($this->sandbox.'/VERSION', "1.0.0\n");
        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::put($this->sandbox.'/.env', 'APP_KEY=original');

        $zipPath = $this->buildPackage(
            ['type' => 'bizmanager-update', 'version' => '1.1.0'],
            [
                '.env' => 'APP_KEY=malicious-overwrite',
                'storage/app/some-tenant-file.jpg' => 'malicious',
            ],
        );

        $result = $this->service()->install($zipPath);

        $this->assertTrue($result['success']);
        $this->assertSame('APP_KEY=original', File::get($this->sandbox.'/.env'));
        $this->assertFalse(File::exists($this->sandbox.'/storage/app/some-tenant-file.jpg'));
    }

    public function test_install_never_overwrites_an_existing_migration_file(): void
    {
        File::put($this->sandbox.'/VERSION', "1.0.0\n");
        File::ensureDirectoryExists($this->sandbox.'/database/migrations');
        File::put($this->sandbox.'/database/migrations/2020_01_01_000000_old.php', 'original migration');

        $zipPath = $this->buildPackage(
            ['type' => 'bizmanager-update', 'version' => '1.1.0'],
            [
                'database/migrations/2020_01_01_000000_old.php' => 'tampered migration',
                'database/migrations/2026_01_01_000000_new.php' => 'a genuinely new migration',
            ],
        );

        $result = $this->service()->install($zipPath);

        $this->assertTrue($result['success']);
        $this->assertSame('original migration', File::get($this->sandbox.'/database/migrations/2020_01_01_000000_old.php'));
        $this->assertSame('a genuinely new migration', File::get($this->sandbox.'/database/migrations/2026_01_01_000000_new.php'));
    }

    public function test_install_rejects_a_package_with_a_path_traversal_entry(): void
    {
        File::put($this->sandbox.'/VERSION', "1.0.0\n");

        $zipPath = storage_path('framework/testing/update-sandbox/'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode(['type' => 'bizmanager-update', 'version' => '1.1.0']));
        $zip->addFromString('files/../../../etc/passwd', 'malicious');
        $zip->close();

        $this->expectExceptionMessage('unsafe file path');
        $this->service()->install($zipPath);
    }

    public function test_rollback_restores_backed_up_files_and_removes_newly_created_ones(): void
    {
        $backupDir = $this->sandbox.'/backup';
        File::ensureDirectoryExists($backupDir.'/app');
        File::put($backupDir.'/app/Existing.php', 'original contents');

        File::ensureDirectoryExists($this->sandbox.'/app');
        File::put($this->sandbox.'/app/Existing.php', 'new contents that should be reverted');
        File::put($this->sandbox.'/app/BrandNew.php', 'should be deleted on rollback');

        $applied = [
            ['relative' => 'app/Existing.php', 'had_backup' => true],
            ['relative' => 'app/BrandNew.php', 'had_backup' => false],
        ];

        $method = new ReflectionMethod(PlatformUpdateService::class, 'rollback');
        $method->setAccessible(true);
        $method->invoke($this->service(), $applied, $backupDir);

        $this->assertSame('original contents', File::get($this->sandbox.'/app/Existing.php'));
        $this->assertFalse(File::exists($this->sandbox.'/app/BrandNew.php'));
    }
}
