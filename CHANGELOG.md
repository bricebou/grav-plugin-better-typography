# v1.0.0
## 09/22/2026

1. [](#new)
    * Grav 2 and Admin2 support (`compatibility: grav: ['1.7', '2.0']`); still works on Grav 1.7.46+
    * The `bettertypo` filter is registered in the Grav 2 Twig content sandbox (`onBuildTwigSandboxPolicy`)
    * Regional language codes (`fr-CA`) fall back to the base language entry (`fr`), then to `default`
    * Hyphenation patterns are resolved from the page language (`en` → `en-US`, `de-AT` → `de`...); unknown languages are skipped with a warning in the Grav log
    * Fix #5: new `singleCharacterWordSpacing` option (`auto`/`enabled`/`disabled`); `auto` no longer glues one-letter words to the next word when the French rules are applied ("à votre service" keeps its space)
    * Admin2: a new per-language entry now shows its language in the collapsed header (the `language` select has a default); the label and help of the select are translated (en/fr)
    * PHPUnit test suite for the typography engine (`composer test`)
    * Development tooling: Rector (PHP 8.3 sets), Easy Coding Standard (PSR-12), PHPStan level 8
2. [](#improved)
    * Requires PHP 8.3+ (Grav 2 baseline, PHP 8.2 reaches end of life in December 2026); `mundschenk-at/php-typography` upgraded to 7.0 (`masterminds/html5` 2.11)
    * `Twig_SimpleFilter` (removed in Twig 3) replaced by `Twig\TwigFilter`; the filter output is marked safe for auto-escaping and plain strings are pre-escaped (no markup injection through the filter)
    * Twig tags still present in the page content (`process: twig: true` without `twig_first`) are protected during processing
    * `dependencies` now declares `php >=8.3` so GPM refuses the install on older PHP
    * HTML parse errors (fragment left untouched by the library) are reported in the Grav log
    * The typography engine and per-language settings are built once per request instead of once per call
    * Configuration values are validated: booleans are coerced (`"1"`, `1`, `"true"`...), unknown quote/dash styles fall back to the defaults with a warning instead of an exception
    * Settings logic moved to `Grav\Plugin\BetterTypography\Typographer`
3. [](#bugfix)
    * Fix #4: `<script>`, `<style>`, `<pre>`, `<code>`, `<kbd>`... were rewritten by the smart quotes/dashes; the library's default ignore list is now applied
    * Hyphenation never had any effect (minimum word lengths were not set); it now works
    * Fix #3: `onPageContentProcessed` processed the main page for every rendered page, so modular sub-pages and collection items were never improved (the Twig filter was required in modular templates); the event's page is now used
    * Smart diacritics were only applied when the Grav language code was exactly `de-DE`/`en-US`; they now follow the toggle and the selected diacritics language
    * French specific rules now also apply to regional codes (`fr-BE`, `fr-CA`...) and to the `default` entry (monolingual sites)
    * No more PHP warning when a `perLanguageSettings` entry has no `language` or when `system.languages.supported` is null

# v0.1.0
##  04/14/2022

1. [](#new)
    * ChangeLog started...
