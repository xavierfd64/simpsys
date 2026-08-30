<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class PlatformUpdateUploadTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $originalVersionContents = null;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests write to the real project's VERSION file (the
        // upload controller always resolves PlatformUpdateService against
        // the real base_path()) — restore it afterward so the repo's own
        // VERSION is never left altered by a test run.
        $this->originalVersionContents = File::exists(base_path('VERSION'))
            ? File::get(base_path('VERSION'))
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalVersionContents !== null) {
            File::put(base_path('VERSION'), $this->originalVersionContents);
        } else {
            File::delete(base_path('VERSION'));
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function buildPackageFile(array $manifest): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'update').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->addFromString('files/app/Placeholder.php', '<?php // placeholder');
        $zip->close();

        return new UploadedFile($zipPath, 'update.zip', 'application/zip', null, true);
    }

    public function test_non_admin_cannot_reach_the_updates_page(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($user)->get('/admin/updates')->assertForbidden();
    }

    public function test_uploading_a_valid_package_stores_it_pending_for_confirmation(): void
    {
        File::put(base_path('VERSION'), "1.0.0\n");
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $file = $this->buildPackageFile(['type' => 'bizmanager-update', 'version' => '1.1.0', 'release_notes' => 'Bug fixes']);

        $this->actingAs($admin)
            ->post('/admin/updates/upload', ['update_zip' => $file])
            ->assertRedirect(route('admin.updates.index'));

        $this->assertNotNull(session('pending_update'));
        $this->assertSame('1.1.0', session('pending_update')['manifest']['version']);

        $this->actingAs($admin)
            ->get('/admin/updates')
            ->assertSee('1.1.0')
            ->assertSee('Bug fixes')
            ->assertSee('Confirm');
    }

    public function test_uploading_an_invalid_package_shows_an_error_and_does_not_queue_anything(): void
    {
        File::put(base_path('VERSION'), "1.0.0\n");
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $file = $this->buildPackageFile(['type' => 'not-a-bizmanager-update', 'version' => '1.1.0']);

        $this->actingAs($admin)
            ->post('/admin/updates/upload', ['update_zip' => $file])
            ->assertRedirect(route('admin.updates.index'));

        $this->assertNull(session('pending_update'));

        $this->actingAs($admin)
            ->get('/admin/updates')
            ->assertSee('not a valid BizManager update package');
    }

    public function test_uploading_an_older_or_same_version_is_rejected(): void
    {
        File::put(base_path('VERSION'), "2.0.0\n");
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $file = $this->buildPackageFile(['type' => 'bizmanager-update', 'version' => '2.0.0']);

        $this->actingAs($admin)->post('/admin/updates/upload', ['update_zip' => $file]);

        $this->assertNull(session('pending_update'));
    }

    public function test_cancelling_a_pending_update_clears_it(): void
    {
        File::put(base_path('VERSION'), "1.0.0\n");
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $file = $this->buildPackageFile(['type' => 'bizmanager-update', 'version' => '1.1.0']);

        $this->actingAs($admin)->post('/admin/updates/upload', ['update_zip' => $file]);
        $this->assertNotNull(session('pending_update'));

        $this->actingAs($admin);
        Livewire::test('pages::admin.updates.index')->call('cancel');

        $this->assertNull(session('pending_update'));
    }
}
