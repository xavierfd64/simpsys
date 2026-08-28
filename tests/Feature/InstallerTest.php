<?php

namespace Tests\Feature;

use App\Services\InstallerService;
use App\Support\EnvEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    public function test_preflight_gate_runs_standalone_without_composer_or_laravel(): void
    {
        // preflight.php runs before vendor/autoload.php is required, so it
        // must survive execution with no autoloader at all — this is the
        // one guarantee that can't be verified by any test that boots
        // through the normal Laravel testing harness.
        $script = base_path('bootstrap/preflight.php');

        $output = shell_exec('php '.escapeshellarg($script).' 2>&1');

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('Uncaught', $output);
    }

    public function test_env_editor_updates_existing_keys_and_appends_missing_ones(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_NAME=Laravel\nDB_HOST=127.0.0.1\n");

        EnvEditor::set($path, [
            'DB_HOST' => 'db.example.com',
            'DB_PASSWORD' => 'has a space',
        ]);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('APP_NAME=Laravel', $contents);
        $this->assertStringContainsString('DB_HOST=db.example.com', $contents);
        $this->assertStringContainsString('DB_PASSWORD="has a space"', $contents);

        unlink($path);
    }

    public function test_installer_service_reports_not_installed_until_locked(): void
    {
        $installer = app(InstallerService::class);

        $this->assertFalse($installer->isInstalled());

        $installer->lock();

        $this->assertTrue($installer->isInstalled());
        $this->assertJson(file_get_contents(storage_path('app/installed.lock')));
    }

    public function test_requirements_check_reports_the_running_php_version(): void
    {
        $requirements = app(InstallerService::class)->requirements();

        $phpCheck = collect($requirements)->firstWhere('label', 'PHP 8.3 or higher');

        $this->assertNotNull($phpCheck);
        $this->assertTrue($phpCheck['passed']);
    }

    public function test_install_middleware_is_a_no_op_in_the_testing_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $this->get('/')->assertOk();
    }

    public function test_wizard_blocks_continuing_past_the_admin_step_without_valid_input(): void
    {
        Livewire::test('pages::install.wizard')
            ->set('step', 3)
            ->call('submitAdmin')
            ->assertHasErrors(['admin_name', 'admin_email', 'admin_password']);
    }

    public function test_wizard_creates_the_platform_admin_and_locks_installation(): void
    {
        Livewire::test('pages::install.wizard')
            ->set('step', 3)
            ->set('admin_name', 'Victoria Admin')
            ->set('admin_email', 'admin-installer@bizmanager.test')
            ->set('admin_password', 'password123')
            ->set('admin_password_confirmation', 'password123')
            ->call('submitAdmin')
            ->assertSet('step', 4);

        $this->assertDatabaseHas('users', [
            'email' => 'admin-installer@bizmanager.test',
            'is_platform_admin' => true,
        ]);

        $this->assertTrue(app(InstallerService::class)->isInstalled());
    }
}
