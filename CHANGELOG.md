# v1.0.0
## 09/22/2026

1. [](#new)
    * Grav 2 and Admin2 support (`compatibility: grav: ['1.7', '2.0']`); still works on Grav 1.7.40+
    * The `bettertypo` filter is registered in the Grav 2 Twig content sandbox (`onBuildTwigSandboxPolicy`)
    * Regional language codes (`fr-CA`) fall back to the base language entry (`fr`), then to `default`
    * Hyphenation patterns are resolved from the page language (`en` → `en-US`, `de-AT` → `de`...); unknown languages are skipped with a warning in the Grav log
    * Development tooling: Rector (PHP 8.2 sets), Easy Coding Standard (PSR-12), PHPStan level 8
2. [](#improved)
    * Requires PHP 8.2+; `mundschenk-at/php-typography` upgraded to 7.0 (`masterminds/html5` 2.11)
    * `Twig_SimpleFilter` (removed in Twig 3) replaced by `Twig\TwigFilter`; the filter output is marked safe for auto-escaping
    * The typography engine and per-language settings are built once per request instead of once per call
    * Configuration values are validated: booleans are coerced (`"1"`, `1`, `"true"`...), unknown quote/dash styles fall back to the defaults with a warning instead of an exception
    * Settings logic moved to `Grav\Plugin\BetterTypography\Typographer`
3. [](#bugfix)
    * Code blocks (`<pre>`, `<code>`, `<kbd>`, `<script>`, `<style>`...) were rewritten by the smart quotes/dashes; the library's default ignore list is now applied
    * Hyphenation never had any effect (minimum word lengths were not set); it now works
    * `onPageContentProcessed` processed the main page for every rendered page (modular sub-pages and collection items were never improved, the main page was processed several times); the event's page is now used
    * Smart diacritics were only applied when the Grav language code was exactly `de-DE`/`en-US`; they now follow the toggle and the selected diacritics language
    * French specific rules now also apply to regional codes (`fr-BE`, `fr-CA`...)
    * No more PHP warning when a `perLanguageSettings` entry has no `language` or when `system.languages.supported` is null

# v0.1.0
##  04/14/2022

1. [](#new)
    * ChangeLog started...
