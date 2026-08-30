<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Applies the Platform Admin's SMTP settings to Laravel's runtime mail
 * config, the same "hot-swap config instead of editing .env" pattern the
 * installer already uses for the database connection (see
 * InstallerService::applyDatabaseConfig()). This lets the admin change
 * SMTP settings from the UI and have every subsequent request's mail use
 * them immediately, with no .env edit, cache clear, or server restart.
 */
class MailConfigurator
{
    /**
     * Read the saved platform settings and apply them, if configured. Safe
     * to call unconditionally on every request (e.g. from a service
     * provider's boot()) — silently no-ops before the app is installed or
     * if the mailer hasn't been configured yet, so mail just falls back to
     * the .env-configured default (typically "log") in that case.
     */
    public static function applyFromDatabase(): void
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return;
            }

            $settings = PlatformSetting::current();
        } catch (Throwable) {
            return;
        }

        if (! $settings->hasMailConfigured()) {
            return;
        }

        static::apply([
            'mailer' => $settings->mail_mailer,
            'host' => $settings->mail_host,
            'port' => $settings->mail_port,
            'encryption' => $settings->mail_encryption,
            'username' => $settings->mail_username,
            'password' => $settings->mail_password,
            'from_address' => $settings->mail_from_address,
            'from_name' => $settings->mail_from_name,
        ]);
    }

    /**
     * Apply an explicit set of mail settings to the runtime config, e.g.
     * for testing not-yet-saved values from the settings form.
     *
     * @param  array{mailer?: ?string, host?: ?string, port?: ?string, encryption?: ?string, username?: ?string, password?: ?string, from_address?: ?string, from_name?: ?string}  $config
     */
    public static function apply(array $config): void
    {
        $mailer = $config['mailer'] ?: 'smtp';

        Config::set('mail.default', $mailer);

        if ($mailer === 'smtp') {
            $scheme = match ($config['encryption'] ?? null) {
                'ssl' => 'smtps',
                default => 'smtp',
            };

            Config::set('mail.mailers.smtp.scheme', $scheme);
            Config::set('mail.mailers.smtp.host', $config['host'] ?? null);
            Config::set('mail.mailers.smtp.port', $config['port'] ?? null);
            Config::set('mail.mailers.smtp.username', $config['username'] ?? null);
            Config::set('mail.mailers.smtp.password', $config['password'] ?? null);
        }

        if (filled($config['from_address'] ?? null)) {
            Config::set('mail.from.address', $config['from_address']);
        }

        if (filled($config['from_name'] ?? null)) {
            Config::set('mail.from.name', $config['from_name']);
        }
    }
}
