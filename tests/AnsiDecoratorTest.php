<?php
/**
 * Part of the "charcoal-dev/ansi" package.
 * @link https://github.com/charcoal-dev/ansi
 */

declare(strict_types=1);

namespace Charcoal\Console\Tests\Ansi;

use Charcoal\Console\Ansi\Ansi;
use Charcoal\Console\Ansi\AnsiDecorator;
use Charcoal\Console\Ansi\Cursor;
use PHPUnit\Framework\TestCase;

/**
 * Implements unit tests for the AnsiDecorator class.
 * Method tests include assertions on initialization, token and map population,
 * idempotency of loading operations, case and alias mappings, cursor sequences, and
 * the removal of ANSI tokens and sequences.
 */
final class AnsiDecoratorTest extends TestCase
{
    protected function setUp(): void
    {
        AnsiDecorator::resetCache();
    }

    public function testInitialStateIsEmpty(): void
    {
        $this->assertSame([], AnsiDecorator::getTokens(), "Tokens should be empty before load.");
        $this->assertSame([], AnsiDecorator::inspect(), "Map should be empty before load.");
    }

    public function testLoadSgrInitializesTokensAndMapOnce(): void
    {
        AnsiDecorator::loadSgr();
        $tokens1 = AnsiDecorator::getTokens();
        $map1 = AnsiDecorator::inspect();

        $this->assertNotEmpty($tokens1, "Tokens should be populated after load.");
        $this->assertNotEmpty($map1, "Map should be populated after load.");
        $this->assertSameSize($tokens1, $map1, "Map keys count should equal tokens count.");
        $this->assertSame(array_values(array_unique($tokens1)), array_values($tokens1), "Tokens must be unique.");

        foreach ($tokens1 as $t) {
            $this->assertSame(strtolower($t), $t, "Tokens must be lowercase.");
            $this->assertArrayHasKey("{".$t."}", $map1, "Each token must exist as a key in map with braces.");
        }

        // Idempotency
        AnsiDecorator::loadSgr();
        $this->assertSame($tokens1, AnsiDecorator::getTokens(), "loadSgr must be idempotent for tokens.");
        $this->assertSame($map1, AnsiDecorator::inspect(), "loadSgr must be idempotent for the map.");
    }

    public function testAnsiCasesAndAliasesProduceProperSgrCodes(): void
    {
        AnsiDecorator::loadSgr();
        $map = AnsiDecorator::inspect();
        $tokens = AnsiDecorator::getTokens();

        foreach (Ansi::cases() as $case) {
            $nameToken = strtolower($case->name);
            $key = "{".$nameToken."}";
            $this->assertContains($nameToken, $tokens, "Ansi case token should be present: ".$nameToken);
            $this->assertArrayHasKey($key, $map, "Ansi case mapping should exist for ".$key);
            $expected = "\e[".$case->value."m";
            $this->assertSame($expected, $map[$key], "Ansi mapping must be exact SGR for case ".$case->name);

            // Aliases, if any
            if (method_exists($case, "alias")) {
                $aliases = $case->alias() ?? [];
                $this->assertIsArray($aliases, "Ansi alias() must return an array or null.");
                foreach ($aliases as $alias) {
                    $aliasToken = strtolower($alias);
                    $aliasKey = "{".$aliasToken."}";
                    $this->assertContains($aliasToken, $tokens, "Ansi alias token should be present: ".$aliasToken);
                    $this->assertArrayHasKey($aliasKey, $map, "Ansi alias mapping must exist: ".$aliasKey);
                    $this->assertSame(
                        $expected,
                        $map[$aliasKey],
                        "Ansi alias mapping must equal its canonical SGR: ".$alias
                    );
                }
            }
        }
    }

    public function testCursorCasesMappingContracts(): void
    {
        AnsiDecorator::loadSgr();
        $map = AnsiDecorator::inspect();
        $tokens = AnsiDecorator::getTokens();

        $cursorCases = Cursor::cases();

        $byName = static function (string $name) use ($cursorCases): ?Cursor {
            foreach ($cursorCases as $c) {
                if ($c->name === $name) {
                    return $c;
                }
            }
            return null;
        };

        // All cursor cases must be represented
        foreach ($cursorCases as $case) {
            $t = strtolower($case->name);
            $key = "{".$t."}";
            $this->assertContains($t, $tokens, "Cursor token should be present: ".$t);
            $this->assertArrayHasKey($key, $map, "Cursor mapping should exist for ".$key);
            $this->assertIsString($map[$key], "Cursor mapping value must be a string.");

            // Cursor sequences should begin with ESC (including goTo which maps to bare ESC)
            $this->assertStringStartsWith("\e", $map[$key], "Cursor sequences must start with ESC.");
        }

        // Special: goTo maps to bare ESC
        $goTo = $byName("goTo");
        if ($goTo !== null) {
            $key = "{".strtolower($goTo->name)."}";
            $this->assertSame("\e", $map[$key], "Cursor goTo must map to a bare ESC character.");
        }

        // Special: directional with default step 1
        foreach (["goUp", "goRight", "goDown", "goLeft"] as $dir) {
            $c = $byName($dir);
            if ($c === null) {
                continue;
            }
            $key = "{".strtolower($c->name)."}";
            $this->assertTrue(
                method_exists($c, "value"),
                "Cursor '".$dir."' should implement a callable 'value' method."
            );
            $expected = "\e".$c->value(1);
            $this->assertSame($expected, $map[$key], "Cursor ".$dir." must map to ESC + value(1).");
        }
    }

    public function testClearTokensRemovesAllRecognizedTokenPatterns(): void
    {
        $input = "A {foo}B {/}C {bar,1}D {baz,12,34}E {///}F {z,9,99}G {invalid,01}H";
        // Note: clearTokens removes tokens of the defined pattern; it does not validate them against known enums.
        // Numeric segments must be 1-99 without a leading zero, so "{invalid,01}" is NOT removed.
        $expected = "A B C D E F G {invalid,01}H";
        $this->assertSame($expected, AnsiDecorator::clearTokens($input));
    }

    public function testClearSeqVariantsRemoveCorrectSequences(): void
    {
        $realA = "\e[31m";
        $realB = "\e[0m";
        $realC = "\e[A";   // cursor
        $lit1 = "\\e[32m";
        $lit2 = "\\x1b[33m";
        $lit3 = "\\033[34m";
        $mixed = "X ".$realA."R".$realB." Y ".$realC." Z ".$lit1." ".$lit2." ".$lit3." T";

        // Default: remove codes only, keep literals
        $out1 = AnsiDecorator::clearSeq($mixed, false, true);
        $this->assertStringNotContainsString("\e[31m", $out1);
        $this->assertStringNotContainsString("\e[0m", $out1);
        $this->assertStringNotContainsString("\e[A", $out1);
        $this->assertStringContainsString("\\e[32m", $out1);
        $this->assertStringContainsString("\\x1b[33m", $out1);
        $this->assertStringContainsString("\\033[34m", $out1);

        // Literals only: remove escaped, keep real codes
        $out2 = AnsiDecorator::clearSeq($mixed, true, false);
        $this->assertStringContainsString("\e[31m", $out2);
        $this->assertStringContainsString("\e[0m", $out2);
        $this->assertStringContainsString("\e[A", $out2);
        $this->assertStringNotContainsString("\\e[32m", $out2);
        $this->assertStringNotContainsString("\\x1b[33m", $out2);
        $this->assertStringNotContainsString("\\033[34m", $out2);

        // Remove both: spaces around removed sequences remain
        $out3 = AnsiDecorator::clearSeq($mixed, true, true);
        $this->assertStringNotContainsString("\e[31m", $out3);
        $this->assertStringNotContainsString("\e[0m", $out3);
        $this->assertStringNotContainsString("\e[A", $out3);
        $this->assertStringNotContainsString("\\e[32m", $out3);
        $this->assertStringNotContainsString("\\x1b[33m", $out3);
        $this->assertStringNotContainsString("\\033[34m", $out3);
        $this->assertSame("X R Y  Z    T", $out3);
    }

    public function testParseWithStripRemovesTokensOnly(): void
    {
        $input = "Hello {foo}World{/}!";
        $expected = AnsiDecorator::clearTokens($input);
        $this->assertSame($expected, AnsiDecorator::parse($input, true, false, true));
        $this->assertSame($expected, AnsiDecorator::parse($input, false, true, true));
    }

    public function testParseReplacesTokensAndAppendsResetByDefault(): void
    {
        AnsiDecorator::loadSgr();
        $map = AnsiDecorator::inspect();

        $ansiCase = Ansi::cases()[0];
        $ansiTok = "{".strtolower($ansiCase->name)."}";

        $cursorCase = Cursor::cases()[0];
        $cursorTok = "{".strtolower($cursorCase->name)."}";

        $input = "A ".$ansiTok."B ".$cursorTok."C";
        $expected = strtr($input, $map)."\e[0m";

        $this->assertSame($expected, AnsiDecorator::parse($input), "Default parse must append reset.");
    }

    public function testParseWithNoSuffixReset(): void
    {
        AnsiDecorator::loadSgr();
        $map = AnsiDecorator::inspect();

        $ansiCase = Ansi::cases()[0];
        $ansiTok = "{".strtolower($ansiCase->name)."}";

        $input = "X ".$ansiTok." Y";
        $expected = strtr($input, $map);

        $this->assertSame($expected, AnsiDecorator::parse($input, false, false, false));
    }

    public function testParseWithLiteralsProducesExportableSequences(): void
    {
        AnsiDecorator::loadSgr();
        $map = AnsiDecorator::inspect();

        $ansiCase = Ansi::cases()[0];
        $ansiTok = "{".strtolower($ansiCase->name)."}";

        $input = "L ".$ansiTok." R";
        $expected = strtr($input, $map)."\e[0m";
        $expectedLit = str_replace("\e[", "\\x1b[", $expected);

        $this->assertSame($expectedLit, AnsiDecorator::parse($input, true, true, false));
    }

    public function testUnknownTokensRemainInParseButCanBeStripped(): void
    {
        $input = "Start {notarealtoken} Middle {/} End";
        $parsed = AnsiDecorator::parse($input, true, false, false);
        $this->assertStringContainsString("{notarealtoken}", $parsed);
        $this->assertStringEndsWith("\e[0m", $parsed);

        $stripped = AnsiDecorator::parse($input, true, false, true);
        $this->assertSame("Start  Middle  End", $stripped);
    }

    public function testGetTokensMatchesInspectKeys(): void
    {
        AnsiDecorator::loadSgr();
        $tokens = AnsiDecorator::getTokens();
        $map = AnsiDecorator::inspect();

        $keys = array_map(
            static fn(string $t) => "{".$t."}",
            $tokens
        );

        sort($keys);
        $mapKeys = array_keys($map);
        sort($mapKeys);

        $this->assertSame($keys, $mapKeys, "getTokens must correspond exactly to inspect() keys.");
    }

    public function testResetCacheClearsAndAllowsReloadWithSameResult(): void
    {
        AnsiDecorator::loadSgr();
        $tokens1 = AnsiDecorator::getTokens();
        $map1 = AnsiDecorator::inspect();

        AnsiDecorator::resetCache();
        $this->assertSame([], AnsiDecorator::getTokens(), "Tokens must be cleared after reset.");
        $this->assertSame([], AnsiDecorator::inspect(), "Map must be cleared after reset.");

        AnsiDecorator::loadSgr();
        $tokens2 = AnsiDecorator::getTokens();
        $map2 = AnsiDecorator::inspect();

        $this->assertSame($tokens1, $tokens2, "Reloaded tokens must match previous.");
        $this->assertSame($map1, $map2, "Reloaded map must match previous.");
    }

    public function testClearSeqDoesNotTouchPlainText(): void
    {
        $input = "No ANSI here.";
        $this->assertSame($input, AnsiDecorator::clearSeq($input));
        $this->assertSame($input, AnsiDecorator::clearSeq($input, true, false));
        $this->assertSame($input, AnsiDecorator::clearSeq($input, true, true));
    }

    public function testClearTokensWithComplexPatterns(): void
    {
        // Ensures regex handles multiple close slashes and numeric segments correctly
        $input = "X {///} Y {abc,9} Z {abc,9,99} W {abc,99,9} Q";
        $expected = "X  Y  Z  W  Q";
        $this->assertSame($expected, AnsiDecorator::clearTokens($input));
    }
}