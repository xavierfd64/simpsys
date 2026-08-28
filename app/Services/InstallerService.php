<?php

namespace App\Services;

use App\Models\User;
use App\Support\EnvEditor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use PDOException;

/**
 * Drives the WordPress-style /install wizard: no step here ever requires the
 * installer/user to hand-edit .env, import SQL, or run a Composer/Artisan
 * command themselves — this service does all of that on their behalf from
 * inside a normal web request.
 */
class InstallerService
{
    public function isInstalled(): bool
    {
        return File::exists($this->lockPath());
    }

    /**
     * @return array<int, array{label: string, passed: bool, detail: string}>
     */
    public function requirements(): array
    {
        // Mirrors bootstrap/preflight.php's own read of composer.json — that
        // file runs before Composer's autoloader exists so it can't share
        // code with this class, but both must report the same real
        // requirement rather than two hardcoded numbers drifting apart.
        $constraint = json_decode(file_get_contents(base_path('composer.json')), true)['require']['php'] ?? '^8.3';
        $displayVersion = ltrim($constraint, '^>=~ ');
        $requiredVersion = implode('.', array_slice(explode('.', $displayVersion.'.0.0'), 0, 3));

        $checks = [
            [
                'label' => "PHP {$displayVersion} or higher",
                'passed' => version_compare(PHP_VERSION, $requiredVersion, '>='),
                'detail' => 'Detected PHP '.PHP_VERSION,
            ],
        ];

        $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'curl', 'dom'];

        foreach ($extensions as $extension) {
            $checks[] = [
                'label' => "PHP extension: {$extension}",
                'passed' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Enabled' : 'Missing — ask your host to enable it',
            ];
        }

        $paths = [
            'storage/' => storage_path(),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
            '.env' => base_path('.env'),
        ];

        foreach ($paths as $label => $path) {
            $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));
            $checks[] = [
                'label' => "Writable: {$label}",
                'passed' => $writable,
                'detail' => $writable ? 'Writable' : 'Not writable — check file/folder permissions',
            ];
        }

        return $checks;
    }

    public function requirementsPassed(): bool
    {
        foreach ($this->requirements() as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attempts a raw PDO connection with the submitted credentials — never
     * Laravel's own DB facade, since the app's configured connection isn't
     * live yet at this point in the wizard. Returns the error message on
     * failure, or null on success.
     *
     * @param  array{host: string, port: string, database: string, username: string, password: string}  $config
     */
    public function testConnection(array $config): ?string
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_TIMEOUT => 5]);

            return null;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Persists the confirmed DB credentials to .env and hot-swaps Laravel's
     * runtime config so migrations can run in this same request — a full
     * page reload isn't needed since nothing before this point ever touched
     * the database.
     *
     * @param  array{host: string, port: string, database: string, username: string, password: string, app_url: string}  $config
     */
    public function applyDatabaseConfig(array $config): void
    {
        EnvEditor::set(base_path('.env'), [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
            'APP_URL' => $config['app_url'],
        ]);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $config['host'],
            'database.connections.mysql.port' => $config['port'],
            'database.connections.mysql.database' => $config['database'],
            'database.connections.mysql.username' => $config['username'],
            'database.connections.mysql.password' => $config['password'],
            'app.url' => $config['app_url'],
        ]);

        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function migrateAndSeed(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        (new SubscriptionPlanSeeder)->run();
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function createAdmin(array $data): User
    {
        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // is_platform_admin/is_active/email_verified_at are deliberately kept
        // out of User's mass-assignable list (they must never be settable
        // from ordinary form input), so this one legitimate call site sets
        // them explicitly instead.
        $admin->forceFill([
            'is_platform_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $admin;
    }

    public function lock(): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath()));

        File::put($this->lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => '1.0',
        ], JSON_PRETTY_PRINT));
    }

    protected function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }
}
