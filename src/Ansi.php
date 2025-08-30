<?php
/**
 * Part of the "charcoal-dev/ansi" package.
 * @link https://github.com/charcoal-dev/ansi
 */

declare(strict_types=1);

namespace Charcoal\Console\Ansi;

/**
 * An enumeration representing ANSI escape codes for terminal text formatting.
 * This enum provides ANSI codes for text styling, foreground colors, and background colors.
 * The ANSI codes can be used to apply styles and colors to terminal output.
 */
enum Ansi: int
{
    // Full reset
    case reset = 0;
    // Reset foreground color
    case reset2 = 39;

    // Text styles
    case bold = 1;
    case dim = 2;
    case italic = 3;
    case underline = 4;
    case blink = 5;
    case blink2 = 6;
    case reverse = 7;
    case hidden = 8;
    case strike = 9;
    case underline2 = 21;
    case overline = 53;

    // Colors (foreground)
    case red = 31;
    case red2 = 91;
    case green = 32;
    case green2 = 92;
    case yellow = 33;
    case yellow2 = 93;
    case blue = 34;
    case blue2 = 94;
    case magenta = 35;
    case magenta2 = 95;
    case cyan = 36;
    case cyan2 = 96;
    case white = 37;
    case white2 = 97;
    case black = 30;
    case grey = 90;

    // Colors (background)
    case bgRed = 41;
    case bgRed2 = 101;
    case bgGreen = 42;
    case bgGreen2 = 102;
    case bgYellow = 43;
    case bgYellow2 = 103;
    case bgBlue = 44;
    case bgBlue2 = 104;
    case bgMagenta = 45;

    /**
     * @return string
     */
    public function value(): string
    {
        return "[" . $this->value . "m";
    }

    /**
     * @return string[]|null
     */
    public function alias(): ?array
    {
        return match ($this) {
            self::reset => ["/"],
            self::bold => ["b"],
            self::underline => ["u"],
            default => null
        };
    }
}