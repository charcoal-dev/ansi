<?php
/**
 * Part of the "charcoal-dev/ansi" package.
 * @link https://github.com/charcoal-dev/ansi
 */

declare(strict_types=1);

namespace Charcoal\Cli\Ansi;

/**
 * Enumeration representing ANSI escape sequences for cursor manipulation.
 * Each case in this enumeration corresponds to a specific cursor movement or
 * command related to terminal screen control.
 */
enum Cursor: string
{
    case goLeft = "[%dD";
    case goRight = "[%dC";
    case goUp = "[%dA";
    case goDown = "[%dB";
    case goTo = "[%d;%dH";
    case atLineStart = "[G";
    case clearLine = "[2K";
    case trimLeft = "[1K";
    case trimRight = "[K";
    case clearScreen = "[2J";
    case clearLeft = "[1J";
    case clearRight = "[J";

    /**
     * @param int $mod1
     * @param int $mod2
     * @return string
     */
    public function value(int $mod1 = 0, int $mod2 = 0): string
    {
        return sprintf($this->value, max(0, $mod1), max(0, $mod2));
    }

    /**
     * @return string[]|null
     */
    public function aliases(): ?array
    {
        return null;
    }
}