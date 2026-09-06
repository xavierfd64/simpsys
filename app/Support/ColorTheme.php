<?php

namespace App\Support;

/**
 * Turns one admin-picked hex color into the small tonal ramp the existing
 * Tailwind design system expects (--color-primary-50/100/200/500/600/700),
 * and validates it won't destroy contrast — an admin picking a pale yellow,
 * for instance, must not silently ship white-on-yellow buttons.
 */
class ColorTheme
{
    public const DEFAULT_PRIMARY = '#2563eb';

    /**
     * Primary buttons/badges render white text on this color, and it's also
     * used as link/text color on the near-white app background — checking
     * contrast against white alone is a good, simple proxy for both.
     */
    public const MIN_CONTRAST_RATIO = 4.5;

    public static function isValidHex(string $hex): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
    }

    public static function isAccessible(string $hex): bool
    {
        return self::isValidHex($hex) && self::contrastRatio($hex, '#ffffff') >= self::MIN_CONTRAST_RATIO;
    }

    /**
     * WCAG 2.x contrast ratio between two hex colors, from 1 (no contrast)
     * to 21 (black on white).
     */
    public static function contrastRatio(string $hexA, string $hexB): float
    {
        $lumA = self::relativeLuminance($hexA);
        $lumB = self::relativeLuminance($hexB);

        $lighter = max($lumA, $lumB);
        $darker = min($lumA, $lumB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    protected static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        $channel = fn (int $c) => $c / 255 <= 0.03928
            ? ($c / 255) / 12.92
            : (($c / 255 + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    /**
     * Lighten/darken $hex toward white/black by a fraction (0-1), keeping
     * the same hue rather than just averaging toward gray.
     */
    protected static function mix(string $hex, float $amount, bool $towardWhite): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $target = $towardWhite ? 255 : 0;

        return self::rgbToHex(
            (int) round($r + ($target - $r) * $amount),
            (int) round($g + ($target - $g) * $amount),
            (int) round($b + ($target - $b) * $amount),
        );
    }

    /**
     * A 50/100/200/500/600/700 ramp around one base color, matching the
     * existing default palette's own shape (500 and 600 the same, 700
     * noticeably darker) so it drops into the current design system without
     * any of the surrounding utility classes needing to change.
     *
     * @return array<string, string>
     */
    public static function shades(string $hex): array
    {
        return [
            '50' => self::mix($hex, 0.94, true),
            '100' => self::mix($hex, 0.85, true),
            '200' => self::mix($hex, 0.65, true),
            '500' => $hex,
            '600' => $hex,
            '700' => self::mix($hex, 0.22, false),
        ];
    }
}
