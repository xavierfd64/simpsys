<?php

namespace App\Support;

class EnvEditor
{
    /**
     * Set (or append) KEY=VALUE pairs in a .env-formatted file in place,
     * leaving every other line untouched.
     *
     * @param  array<string, string>  $values
     */
    public static function set(string $path, array $values): void
    {
        $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $remaining = $values;

        foreach ($lines as $i => $line) {
            foreach ($remaining as $key => $value) {
                if (preg_match('/^'.preg_quote($key, '/').'=/', $line)) {
                    $lines[$i] = $key.'='.static::formatValue($value);
                    unset($remaining[$key]);
                    break;
                }
            }
        }

        foreach ($remaining as $key => $value) {
            $lines[] = $key.'='.static::formatValue($value);
        }

        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    protected static function formatValue(mixed $value): string
    {
        $value = (string) $value;

        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
