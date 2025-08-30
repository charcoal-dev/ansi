<?php
/**
 * Part of the "charcoal-dev/ansi" package.
 * @link https://github.com/charcoal-dev/ansi
 */

declare(strict_types=1);

namespace Charcoal\Console\Ansi;

/**
 * Handles the decoration and parsing of ANSI escape codes for styling text outputs.
 */
final class AnsiDecorator
{
    private static bool $sgrLoaded = false;
    private static array $tokens = [];
    private static array $map = [];

    /**
     * Initializes required vectors and caches.
     */
    public static function loadSgr(): void
    {
        if (self::$sgrLoaded) {
            return;
        }

        $codes = [];
        foreach (Ansi::cases() as $case) {
            $codes[strtolower($case->name)] = "\e[" . $case->value . "m";
            foreach ($case->alias() ?? [] as $alias) {
                $codes[strtolower($alias)] = "\e[" . $case->value . "m";
            }
        }

        foreach (Cursor::cases() as $case) {
            $codes[strtolower($case->name)] = "\e" . match ($case) {
                    Cursor::goUp, Cursor::goRight, Cursor::goDown, Cursor::goLeft => $case->value(1),
                    Cursor::goTo => "",
                    default => $case->value,
                };
        }

        // Select codes
        self::$tokens = array_keys($codes);
        $tokens = array_map(fn($k) => "{" . $k . "}", self::$tokens);
        self::$map = array_combine(array_values($tokens), array_values($codes));
        self::$sgrLoaded = true;
    }

    /**
     * Removes all tokens (!= ANSI codes) from the input string.
     */
    public static function clearTokens(string $input): string
    {
        return preg_replace('/\{(?:([a-z]+(,?[1-9][0-9]?){0,2})|\/)+}/i', "", $input);
    }

    public static function clearSeq(string $input, bool $literals = false, bool $escapes = true): string
    {
        // @language=RegExp
        $literals = $literals ? '' : '\e';
        return preg_replace(match (true) {
            $literals && !$escapes => '/(\\(e|x1b|033)\[[0-9;]*(m|[A-Z]))/i',
            $literals && $escapes => '/((\e|(\\(e|x1b|033)))\[[0-9;]*(m|[A-Z]))/i',
            default => '',
        }, "", $input);
    }

    /**
     * Parses the input string and returns the decorated output.
     * @param string $input The input string to be parsed.
     * @param bool $suffixReset Whether to reset the suffix after parsing.
     * @param bool $literals Whether to return the literal (exportable) ANSI codes.
     * @param bool $strip Whether to strip all tokens from the input string.
     * @return string The decorated output.
     */
    public static function parse(
        string $input,
        bool   $suffixReset = true,
        bool   $literals = false,
        bool   $strip = false,
    ): string
    {
        if ($strip) {
            return self::clearTokens($input);
        }

        if (!self::$sgrLoaded) {
            self::loadSgr();
        }

        $parsed = strtr($input, self::$map) . ($suffixReset ? "\e[0m" : "");
        return !$literals ? $parsed : str_replace("\e", "\\x1b", $parsed);
    }

    /**
     * @return string[]
     */
    public static function getTokens(): array
    {
        return self::$tokens;
    }

    /**
     * @return array<string, string>
     */
    public static function inspect(): array
    {
        return self::$map;
    }

    /**
     * Resets the internal cache and re-initializes static properties to their default states.
     */
    public static function resetCache(): void
    {
        self::$sgrLoaded = false;
        self::$tokens = [];
        self::$map = [];
    }
}