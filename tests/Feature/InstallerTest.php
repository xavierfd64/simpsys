<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfNotInstalled;
use App\Services\InstallerService;
use App\Support\EnvEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/installed.lock'));
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    /**
     * RedirectIfNotInstalled no-ops entirely in the testing environment (see
     * its own docblock), so exercising its real branches means temporarily
     * telling it we're not in testing — otherwise this exact regression
     * (Livewire's own AJAX endpoint getting redirected to /install mid-wizard,
     * which looks like the Continue button just reloading the page) would be
     * invisible to the whole suite, the same blind spot documented in
     * CLAUDE.md for the Stage 5 persistent-middleware bug.
     */
    public function test_livewire_ajax_requests_are_exempt_from_the_install_redirect(): void
    {
        File::delete(storage_path('app/installed.lock'));
        $this->app['env'] = 'production';

        $middleware = new RedirectIfNotInstalled;
        $next = fn ($request) => new Response('handled');

        $livewireRequest = Request::create('/livewire-anyhash123/update', 'POST');
        $livewireRequest->headers->set('X-Livewire', 'true');
        // Livewire::isLivewireRequest() reads the container-bound request()
        // helper, not the $request parameter handed to the middleware — the
        // real HTTP kernel binds them to the same instance before dispatch,
        // so this mirrors that instead of testing a stale ambient request.
        $this->app->instance('request', $livewireRequest);

        $this->assertSame(
            'handled',
            $middleware->handle($livewireRequest, $next)->getContent(),
            'A Livewire AJAX call must reach the app, not get redirected to /install.'
        );

        $ordinaryRequest = Request::create('/some-other-page', 'GET');
        $this->app->instance('request', $ordinaryRequest);

        $this->assertSame(
            302,
            $middleware->handle($ordinaryRequest, $next)->getStatusCode(),
            'A normal page request must still redirect to /install when not installed.'
        );
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

    /**
     * public/storage only exists in local dev because `storage:link` was
     * run once, manually, during initial project setup — the installer
     * itself never called it, so every uploaded image (product photos,
     * receipts, tenant logos) would silently 404 on a fresh shared-hosting
     * install: the upload and database write both succeed, but the URL
     * Laravel generates never resolves to a real file. Removes the real
     * symlink and confirms migrateAndSeed() recreates it, then restores it
     * so this test doesn't leave the dev environment link missing.
     */
    public function test_migrate_and_seed_creates_the_public_storage_link(): void
    {
        $link = public_path('storage');
        $existed = file_exists($link);

        if ($existed) {
            unlink($link);
        }

        $this->assertFalse(file_exists($link));

        app(InstallerService::class)->migrateAndSeed();

        $this->assertTrue(file_exists($link), 'storage:link was not run during install.');

        if (! $existed) {
            unlink($link);
        }
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
