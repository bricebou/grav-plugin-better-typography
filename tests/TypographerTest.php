<?php

declare(strict_types=1);

namespace Grav\Plugin\BetterTypography\Tests;

use Grav\Plugin\BetterTypography\Typographer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Typographer::class)]
final class TypographerTest extends TestCase
{
    /**
     * The HTML5 serializer writes U+00A0 as an entity.
     */
    private const string NBSP = '&nbsp;';

    private const string SHY = "\u{00AD}";

    /**
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * @param iterable<mixed> $config
     */
    private function typographer(iterable $config): Typographer
    {
        $this->warnings = [];

        return new Typographer($config, function (string $message): void {
            $this->warnings[] = $message;
        });
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function french(array $overrides = []): array
    {
        return array_merge([
            'language' => 'fr',
            'useSmartQuotes' => true,
            'smartQuotesStyle' => 'doubleGuillemetsFrench',
            'smartQuotesStyleSecondary' => 'doubleCurled',
            'useSmartDashes' => true,
            'smartDashesStyle' => 'international',
            'applyHyphenations' => false,
            'applyFrenchSpecific' => true,
        ], $overrides);
    }

    public function testDefaultEntryAppliesCurlyQuotesAndInternationalDashes(): void
    {
        $result = $this->typographer([[
            'language' => 'default',
        ]])->process('<p>She said "hello" -- then left...</p>');

        self::assertSame('<p>She said “hello” – then' . self::NBSP . 'left…</p>', $result); // dewidow glues the last word
    }

    #[TestDox('Issue #4: code, pre, script and style content is never altered')]
    public function testIgnoredTagsAreLeftUntouched(): void
    {
        $html = '<p>"quoted"</p><pre><code>echo "hi" -- 1st;</code></pre>'
            . '<script>x("1")</script><style>a::before { content: "--"; }</style>';

        $result = $this->typographer([[
            'language' => 'default',
        ]])->process($html);

        self::assertStringContainsString('<p>“quoted”</p>', $result);
        self::assertStringContainsString('<code>echo "hi" -- 1st;</code>', $result);
        self::assertStringContainsString('<script>x("1")</script>', $result);
        self::assertStringContainsString('<style>a::before { content: "--"; }</style>', $result);
    }

    public function testFrenchRulesApplyGuillemetsPunctuationSpacingAndRomanOrdinals(): void
    {
        $result = $this->typographer([$this->french()])->process('<p>Il a dit : "bonjour" ! Au XVIe siecle ?</p>', 'fr');

        self::assertSame(
            '<p>Il a dit' . self::NBSP . ': «' . self::NBSP . 'bonjour' . self::NBSP . '»' . self::NBSP . '! '
            . 'Au XVI<sup class="ordinal">e</sup> siecle' . self::NBSP . '?</p>',
            $result,
        );
    }

    public function testRegionalLanguageFallsBackToItsPrimarySubtagEntry(): void
    {
        $typographer = $this->typographer([[
            'language' => 'default',
        ], $this->french()]);

        self::assertStringContainsString('«' . self::NBSP . 'a' . self::NBSP . '»', $typographer->process('<p>"a"</p>', 'fr-CA'));
        self::assertStringContainsString('«' . self::NBSP . 'a' . self::NBSP . '»', $typographer->process('<p>"a"</p>', 'FR_fr'));
        self::assertStringContainsString('“a”', $typographer->process('<p>"a"</p>', 'de'));
    }

    #[TestDox('Monolingual sites only reach the "default" entry: its French toggle is honoured')]
    public function testDefaultEntryCanApplyFrenchRules(): void
    {
        $result = $this->typographer([[
            'language' => 'default',
            'applyFrenchSpecific' => true,
        ]])
            ->process('<p>Bonjour : ca va ? Nous sommes a votre service.</p>');

        self::assertSame('<p>Bonjour' . self::NBSP . ': ca va' . self::NBSP . '? Nous sommes a votre service.</p>', $result);
    }

    #[TestDox('Issue #5: one-letter words keep a plain space under the French rules ("à votre service")')]
    public function testSingleCharacterWordSpacingIsDisabledByFrenchRulesUnlessForced(): void
    {
        $sentence = '<p>Nous sommes a votre service ici.</p>';

        self::assertSame($sentence, $this->typographer([$this->french()])->process($sentence, 'fr'));
        self::assertSame(
            '<p>Nous sommes a' . self::NBSP . 'votre service ici.</p>',
            $this->typographer([
                $this->french([
                    'singleCharacterWordSpacing' => 'enabled',
                ])])->process($sentence, 'fr'),
        );
        self::assertSame(
            '<p>Nous sommes a' . self::NBSP . 'votre service ici.</p>',
            $this->typographer([
                $this->french([
                    'singleCharacterWordSpacing' => true,
                ])])->process($sentence, 'fr'),
        );
    }

    #[DataProvider('singleCharacterWordSpacingProvider')]
    public function testSingleCharacterWordSpacingModes(mixed $mode, bool $expectedGlue): void
    {
        $result = $this->typographer([[
            'language' => 'en',
            'singleCharacterWordSpacing' => $mode,
        ]])
            ->process('<p>There is a word in this sentence.</p>', 'en');

        self::assertSame($expectedGlue, str_contains($result, 'a' . self::NBSP . 'word'), var_export($mode, true));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function singleCharacterWordSpacingProvider(): iterable
    {
        yield 'auto (non French => enabled)' => ['auto', true];
        yield 'missing' => [null, true];
        yield 'enabled' => ['enabled', true];
        yield 'disabled' => ['disabled', false];
        yield 'bool false' => [false, false];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'garbage falls back to auto' => ['whatever', true];
    }

    public function testHyphenationUsesPatternsMatchingThePageLanguage(): void
    {
        $typographer = $this->typographer([
            [
                'language' => 'en',
                'applyHyphenations' => true,
            ],
            [
                'language' => 'fr',
                'applyHyphenations' => true,
            ],
            [
                'language' => 'de',
                'applyHyphenations' => 'true',
            ],
        ]);

        self::assertStringContainsString('extra' . self::SHY . 'or' . self::SHY . 'di' . self::SHY . 'nary', $typographer->process('<p>extraordinary considerations here</p>', 'en'));
        self::assertStringContainsString('anti' . self::SHY . 'cons' . self::SHY . 'ti', $typographer->process('<p>anticonstitutionnellement toujours</p>', 'fr'));
        self::assertStringContainsString('Donau' . self::SHY . 'dampf', $typographer->process('<p>Donaudampfschifffahrtsgesellschaft heute</p>', 'de-AT'));
        self::assertSame([], $this->warnings);
    }

    public function testHyphenationIsSkippedWithAWarningWhenNoPatternsExist(): void
    {
        $html = '<p>extraordinary considerations here</p>';
        $typographer = $this->typographer([[
            'language' => 'xx',
            'applyHyphenations' => true,
        ]]);

        self::assertSame($html, $typographer->process($html, 'xx'));
        self::assertCount(1, $this->warnings);
        self::assertStringContainsString('No hyphenation patterns available for language "xx"', $this->warnings[0]);
    }

    public function testSmartDiacriticsFollowTheSelectedReplacementList(): void
    {
        $typographer = $this->typographer([
            [
                'language' => 'en',
                'useSmartDiacritics' => true,
                'smartDiacriticsLanguage' => 'en-US',
            ],
            [
                'language' => 'de',
                'useSmartDiacritics' => true,
                'smartDiacriticsLanguage' => 'xx-XX',
            ],
            [
                'language' => 'fr',
                'useSmartDiacritics' => false,
                'smartDiacriticsLanguage' => 'en-US',
            ],
        ]);

        self::assertSame('<p>crème brûlée</p>', $typographer->process('<p>creme brulee</p>', 'en'));
        self::assertSame('<p>creme brulee</p>', $typographer->process('<p>creme brulee</p>', 'de'));
        self::assertSame('<p>creme brulee</p>', $typographer->process('<p>creme brulee</p>', 'fr'));
        self::assertCount(1, $this->warnings);
        self::assertStringContainsString('Unknown diacritics language "xx-xx"', $this->warnings[0]);
    }

    public function testUnknownStylesFallBackToDefaultsWithAWarning(): void
    {
        $result = $this->typographer([[
            'language' => 'default',
            'smartQuotesStyle' => 'bogus',
            'smartDashesStyle' => 'nope',
        ]])
            ->process('<p>"a" -- b</p>');

        self::assertSame('<p>“a” –' . self::NBSP . 'b</p>', $result);
        self::assertCount(2, $this->warnings);
        self::assertStringContainsString('Unknown style "bogus" for set_smart_quotes_primary', $this->warnings[0]);
        self::assertStringContainsString('Unknown style "nope" for set_smart_dashes_style', $this->warnings[1]);
    }

    #[DataProvider('booleanProvider')]
    public function testHandWrittenBooleansAreCoerced(mixed $value, bool $quotesExpected): void
    {
        $result = $this->typographer([[
            'language' => 'default',
            'useSmartQuotes' => $value,
        ]])->process('<p>"a"</p>');

        self::assertSame($quotesExpected ? '<p>“a”</p>' : '<p>"a"</p>', $result, var_export($value, true));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function booleanProvider(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string false' => ['false', false];
        yield 'yes' => ['yes', true];
        yield 'null uses default' => [null, true];
        yield 'garbage uses default' => ['maybe', true];
    }

    public function testEntriesWithoutLanguageBecomeTheDefaultAndInvalidEntriesAreIgnored(): void
    {
        $typographer = $this->typographer([
            'garbage',
            42,
            [
                'useSmartQuotes' => false,
            ],
        ]);

        self::assertSame('<p>"a" –' . self::NBSP . 'b</p>', $typographer->process('<p>"a" -- b</p>', 'it'));
    }

    public function testBlankInputIsReturnedAsIs(): void
    {
        $typographer = $this->typographer([]);

        self::assertSame('', $typographer->process(''));
        self::assertSame("  \n", $typographer->process("  \n", 'fr'));
    }

    public function testTwigTagsArePreservedOnRequest(): void
    {
        $html = '<p>Texte "cité" -- {{ \'Un "texte"\'|bettertypo }} {% if a == "b" %}x{% endif %} {# "c" #}</p>';
        $typographer = $this->typographer([$this->french()]);

        self::assertSame(
            '<p>Texte «' . self::NBSP . 'cité' . self::NBSP . '» – {{ \'Un "texte"\'|bettertypo }} {% if a == "b" %}x{% endif %} {# "c" #}</p>',
            $typographer->process($html, 'fr', true),
        );
        self::assertStringContainsString('{% if a == «', $typographer->process($html, 'fr', false));
    }

    public function testMalformedHtmlIsLeftUntouchedAndReportedOnce(): void
    {
        $html = '<p>He said "hi" -- <div>bad</p></div>';
        $typographer = $this->typographer([[
            'language' => 'default',
        ]]);

        self::assertSame($html, $typographer->process($html));
        self::assertSame($html, $typographer->process($html));
        self::assertCount(1, $this->warnings);
        self::assertStringContainsString('HTML parse error', $this->warnings[0]);
    }

    #[DataProvider('languageProvider')]
    public function testNormalizeLanguage(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, Typographer::normalizeLanguage($input));
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function languageProvider(): iterable
    {
        yield 'lower-cased' => ['FR', 'fr'];
        yield 'underscore' => ['fr_FR', 'fr-fr'];
        yield 'trimmed' => [' en ', 'en'];
        yield 'empty' => ['', null];
        yield 'not a string' => [12, null];
        yield 'null' => [null, null];
    }
}
