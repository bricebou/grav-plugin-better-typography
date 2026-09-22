<?php

declare(strict_types=1);

namespace Grav\Plugin\BetterTypography;

use Closure;
use PHP_Typography\Exceptions\Invalid_Style_Exception;
use PHP_Typography\PHP_Typography;
use PHP_Typography\Settings;
use PHP_Typography\Settings\Dash_Style;
use PHP_Typography\Settings\Quote_Style;

/**
 * Turns the plugin configuration into PHP-Typography settings and processes HTML fragments.
 *
 * One Settings object is built (and cached) per language and the PHP_Typography engine is
 * shared, so hyphenation patterns are loaded at most once per request.
 *
 * PHP-Typography is an HTML *transformer*, not a sanitizer: whatever markup goes in comes out
 * (scripts and event handler attributes included). Only pass it content you already trust.
 */
final class Typographer
{
    public const DEFAULT_LANGUAGE = 'default';

    /**
     * Grav language codes that have no exact hyphenation pattern file but an obvious best match.
     */
    private const HYPHENATION_ALIASES = [
        'en' => 'en-US',
        'el' => 'el-Mono',
        'mn' => 'mn-Cyrl',
        'sh' => 'sh-Latn',
        'sr' => 'sr-Cyrl',
        'zh' => 'zh-Latn',
    ];

    /**
     * Configuration keyed by (lower-cased) language code.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $perLanguage = [];

    /**
     * @var array<string, Settings>
     */
    private array $settingsCache = [];

    private ?PHP_Typography $engine = null;

    /**
     * Messages already logged, so a misconfiguration is reported once per request.
     *
     * @var array<string, true>
     */
    private array $logged = [];

    /**
     * Twig delimiters, protected while the typography runs when Twig is processed after us.
     */
    private const TWIG_PATTERN = '/\{#.*?#\}|\{%.*?%\}|\{\{.*?\}\}/s';

    private const PLACEHOLDER_PATTERN = '/\x{27E6}(\d+)\x{27E7}/u';

    /**
     * @param iterable<mixed>              $perLanguageSettings The raw `perLanguageSettings` list of the plugin configuration.
     * @param (Closure(string): void)|null $warn                Receives configuration/processing warnings (typically the Grav logger).
     */
    public function __construct(
        iterable $perLanguageSettings,
        private readonly ?Closure $warn = null,
    ) {
        foreach ($perLanguageSettings as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $language = self::normalizeLanguage($entry['language'] ?? null) ?? self::DEFAULT_LANGUAGE;
            $this->perLanguage[$language] = $entry;
        }
    }

    /**
     * Applies the typographic rules configured for $language (or for "default") to an HTML fragment.
     *
     * With $preserveTwig, Twig tags/expressions/comments still present in the fragment (Grav processes
     * content Twig after `onPageContentProcessed` unless `twig_first` is set) are left untouched.
     */
    public function process(string $html, ?string $language = null, bool $preserveTwig = false): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $language = self::normalizeLanguage($language) ?? self::DEFAULT_LANGUAGE;
        $this->settingsCache[$language] ??= $this->buildSettings($language);

        /** @var list<string> $twig */
        $twig = [];
        if ($preserveTwig) {
            $html = preg_replace_callback(self::TWIG_PATTERN, static function (array $match) use (&$twig): string {
                $twig[] = $match[0];

                return "\u{27E6}" . (count($twig) - 1) . "\u{27E7}";
            }, $html) ?? $html;
        }

        $processed = (string) $this->engine()
            ->process($html, $this->settingsCache[$language]);

        if ($twig !== []) {
            return preg_replace_callback(
                self::PLACEHOLDER_PATTERN,
                static fn (array $match): string => $twig[(int) $match[1]] ?? $match[0],
                $processed
            ) ?? $processed;
        }

        return $processed;
    }

    /**
     * Lower-cased, trimmed language code, with underscores turned into hyphens (fr_FR => fr-fr).
     */
    public static function normalizeLanguage(mixed $language): ?string
    {
        if (! is_string($language)) {
            return null;
        }

        $language = strtolower(str_replace('_', '-', trim($language)));

        return $language === '' ? null : $language;
    }

    private function engine(): PHP_Typography
    {
        return $this->engine ??= new PHP_Typography();
    }

    /**
     * Configuration entry for a language ("fr-ca"), falling back to its primary subtag ("fr"),
     * then to the "default" entry, then to an empty set.
     *
     * @return array<string, mixed>
     */
    private function configFor(string $language): array
    {
        return $this->perLanguage[$language]
            ?? $this->perLanguage[$this->primarySubtag($language)]
            ?? $this->perLanguage[self::DEFAULT_LANGUAGE]
            ?? [];
    }

    private function buildSettings(string $language): Settings
    {
        $config = $this->configFor($language);
        $settings = new Settings(false);

        // Content that must never be altered: code samples, scripts, styles, form controls...
        // (library defaults). Without these the smart quotes were rewriting <pre><code> blocks.
        $settings->set_tags_to_ignore();
        $settings->set_classes_to_ignore();
        $settings->set_ids_to_ignore();
        // Malformed HTML: leave the fragment untouched (library behaviour) but say so in the log.
        $settings->set_ignore_parser_errors(false);
        $settings->set_parser_errors_handler(function (array $errors) use ($language): array {
            $this->warnOnce(sprintf(
                'HTML parse error in a "%s" fragment, typography skipped for it: %s',
                $language,
                (string) (reset($errors) ?: 'unknown error'),
            ));

            return $errors;
        });

        // Always-on improvements.
        $settings->set_smart_ordinal_suffix(true);
        $settings->set_smart_ellipses(true);
        $settings->set_smart_marks(true);
        $settings->set_smart_exponents(true);
        $settings->set_smart_fractions(true);
        $settings->set_smart_area_units(true);
        $settings->set_fraction_spacing(true);
        $settings->set_unit_spacing(true);
        $settings->set_units();
        $settings->set_numbered_abbreviation_spacing(true);
        $settings->set_dewidow(true);
        $settings->set_max_dewidow_length();
        $settings->set_max_dewidow_pull();
        $settings->set_dewidow_word_number();

        // Smart quotes.
        $useSmartQuotes = $this->toBool($config['useSmartQuotes'] ?? null, true);
        $settings->set_smart_quotes($useSmartQuotes);
        if ($useSmartQuotes) {
            $settings->set_smart_quotes_exceptions();
            $this->applyStyle($settings, 'set_smart_quotes_primary', $config['smartQuotesStyle'] ?? null, Quote_Style::DOUBLE_CURLED, $language);
            $this->applyStyle($settings, 'set_smart_quotes_secondary', $config['smartQuotesStyleSecondary'] ?? null, Quote_Style::SINGLE_CURLED, $language);
        }

        // Smart dashes.
        $useSmartDashes = $this->toBool($config['useSmartDashes'] ?? null, true);
        $settings->set_smart_dashes($useSmartDashes);
        if ($useSmartDashes) {
            $this->applyStyle($settings, 'set_smart_dashes_style', $config['smartDashesStyle'] ?? null, Dash_Style::INTERNATIONAL, $language);
        }

        // Hyphenation.
        $hyphenationLanguage = $this->toBool($config['applyHyphenations'] ?? null, false)
            ? $this->resolveHyphenationLanguage($language)
            : null;
        $settings->set_hyphenation($hyphenationLanguage !== null);
        if ($hyphenationLanguage !== null) {
            $settings->set_hyphenation_language($hyphenationLanguage);
            $settings->set_min_length_hyphenation();
            $settings->set_min_before_hyphenation();
            $settings->set_min_after_hyphenation();
            $settings->set_hyphenate_headings();
            $settings->set_hyphenate_all_caps();
            $settings->set_hyphenate_title_case();
            $settings->set_hyphenate_compounds();
            $settings->set_hyphenation_exceptions();
        }

        // French: narrow no-break space before double punctuation, "XVIe" => XVI<sup>e</sup>.
        // The "default" entry is the only one a monolingual site reaches, so its toggle is trusted.
        $isFrench = $this->primarySubtag($language) === 'fr' || $language === self::DEFAULT_LANGUAGE;
        $applyFrench = $isFrench && $this->toBool($config['applyFrenchSpecific'] ?? null, false);
        $settings->set_french_punctuation_spacing($applyFrench);
        $settings->set_smart_ordinal_suffix_match_roman_numerals($applyFrench);

        // Glue one-letter words to the next word ("a&nbsp;word"). Not a French rule ("à votre service"
        // must keep its plain space): "auto" means enabled unless the French rules are applied.
        $settings->set_single_character_word_spacing(
            $this->singleCharacterWordSpacing($config['singleCharacterWordSpacing'] ?? null, $applyFrench)
        );

        // Diacritics ("creme brulee" => "crème brûlée"), only available for a few languages.
        $diacriticsLanguage = $this->toBool($config['useSmartDiacritics'] ?? null, false)
            ? $this->resolveDiacriticsLanguage($config['smartDiacriticsLanguage'] ?? null, $language)
            : null;
        $settings->set_smart_diacritics($diacriticsLanguage !== null);
        if ($diacriticsLanguage !== null) {
            $settings->set_diacritic_language($diacriticsLanguage);
            $settings->set_diacritic_custom_replacements();
        }

        return $settings;
    }

    /**
     * Applies a quote or dash style, falling back to $default when the configured value is unknown
     * (PHP-Typography would otherwise throw and take the whole page down).
     */
    private function applyStyle(Settings $settings, string $setter, mixed $style, string $default, string $language): void
    {
        $style = is_string($style) && $style !== '' ? $style : $default;

        try {
            $settings->{$setter}($style);
        } catch (Invalid_Style_Exception) {
            $this->warnOnce(sprintf(
                'Unknown style "%s" for %s (language "%s"), using "%s" instead.',
                $style,
                $setter,
                $language,
                $default,
            ));
            $settings->{$setter}($default);
        }
    }

    /**
     * Maps a Grav language code onto one of the hyphenation pattern files shipped with PHP-Typography
     * ("fr" => "fr", "en" => "en-US", "de-at" => "de", ...). Returns null when none matches.
     */
    private function resolveHyphenationLanguage(string $language): ?string
    {
        if ($language === self::DEFAULT_LANGUAGE) {
            $this->warnOnce('Hyphenation is enabled for the "default" entry but the page has no language: nothing to hyphenate with.');

            return null;
        }

        /** @var array<string, string>|null $available lower-cased code => actual code */
        static $available = null;
        if ($available === null) {
            $available = [];
            foreach (array_keys(PHP_Typography::get_hyphenation_languages()) as $code) {
                $available[strtolower((string) $code)] = (string) $code;
            }
        }

        $primary = $this->primarySubtag($language);
        $candidates = [$language, strtolower(self::HYPHENATION_ALIASES[$primary] ?? ''), $primary];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset($available[$candidate])) {
                return $available[$candidate];
            }
        }

        foreach ($available as $lower => $code) {
            if (str_starts_with($lower, $primary . '-')) {
                return $code;
            }
        }

        $this->warnOnce(sprintf('No hyphenation patterns available for language "%s"; hyphenation skipped.', $language));

        return null;
    }

    private function resolveDiacriticsLanguage(mixed $configured, string $language): ?string
    {
        $wanted = self::normalizeLanguage($configured);
        if ($wanted === null) {
            $this->warnOnce(sprintf('Smart diacritics are enabled for language "%s" but no diacritics language is selected.', $language));

            return null;
        }

        foreach (array_keys(PHP_Typography::get_diacritic_languages()) as $code) {
            if (strtolower((string) $code) === $wanted) {
                return (string) $code;
            }
        }

        $this->warnOnce(sprintf('Unknown diacritics language "%s" (language "%s"); diacritics skipped.', $wanted, $language));

        return null;
    }

    private function singleCharacterWordSpacing(mixed $configured, bool $applyFrench): bool
    {
        if (is_bool($configured) || is_int($configured)) {
            return $this->toBool($configured, ! $applyFrench);
        }

        $mode = is_string($configured) ? strtolower(trim($configured)) : '';

        return match ($mode) {
            'enabled', 'on', 'true', 'yes', '1' => true,
            'disabled', 'off', 'false', 'no', '0' => false,
            default => ! $applyFrench,
        };
    }

    /**
     * Configuration values may come from YAML written by hand ("1", 1, "true", yes...) while
     * PHP-Typography setters strictly require booleans.
     */
    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function primarySubtag(string $language): string
    {
        return explode('-', $language, 2)[0];
    }

    private function warnOnce(string $message): void
    {
        if (isset($this->logged[$message])) {
            return;
        }

        $this->logged[$message] = true;
        if ($this->warn instanceof Closure) {
            ($this->warn)('[better-typography] ' . $message);
        }
    }
}
