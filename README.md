# Better Typography Plugin

The **Better Typography** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav).
It automatically improves the typography of your content and provides a Twig filter.

## Requirements

- PHP 8.2 or higher (the `mbstring` extension is required)
- Grav 1.7.40+ or Grav 2.x (compatible with the classic Admin plugin and with Admin2)

## Installation

Installing the Better Typography plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install better-typography

This will install the Better Typography plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/better-typography`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `better-typography`. You can find these files on [GitHub](https://github.com/bricebou/grav-plugin-better-typography) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/better-typography

> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/bricebou/grav-plugin-better-typography/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/better-typography/better-typography.yaml` to `user/config/plugins/better-typography.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
perLanguageSettings:
  -                                           # You can add as many languages as defined as supported language in system.yaml
    language: default                         #
    useSmartQuotes: true                      # replace "foo" with &ldquo;foo&rdquo; or &laquo;&nbsp;foo&nbsp;&raquo;... Depends on the selected quoteStyle
    smartQuotesStyle: doubleCurled            # Style to apply for quotes ; available options are 'doubleCurled' (&ldquo;foo&rdquo;), 'doubleCurledReversed' (&rdquo;foo&rdquo;) 'doubleLow9' (&bdquo;foo&rdquo;), 'doubleLow9Reversed' (&bdquo;foo&ldquo;), 'singleCurled' (&lsquo;foo&rsquo;), 'singleCurledReversed' (&rsquo;foo&rsquo;), 'singleLow9' (&sbquo;foo&rsquo;), 'singleLow9Reversed' (&sbquo;foo&lsquo;), 'doubleGuillemetsFrench' (&laquo;&nbsp;foo&nbsp;&raquo;), 'doubleGuillemets' (&laquo;foo&raquo;), 'doubleGuillemetsReversed' (&raquo;foo&laquo;), 'singleGuillemets' (&lsaquo;foo&rsaquo;), 'singleGuillemetsReversed' (&rsaquo;foo&lsaquo;) 'cornerBrackets' (&#x300c;foo&#x300d;), 'whiteCornerBracket' (&#x300e;foo&#x300f;).
    smartQuotesStyleSecondary: singleCurled   # same as above for smartQuotesStyle
    useSmartDashes: true                      # replace -- & --- to en & em dashes, depending on the selected dashStyle
    smartDashesStyle: international           # 'international' or 'traditionalUS'
    applyHyphenations: false
    applyFrenchSpecific: false                # apply specific french typographic rules such as unbreakable space before double punctuation (?, !, :, ;) and XVI<sup>e</sup> siècle
    singleCharacterWordSpacing: auto          # 'auto', 'enabled' or 'disabled': glue one-letter words to the next word ("a&nbsp;word"); 'auto' = enabled unless applyFrenchSpecific is on ("à votre service" keeps its space)
    useSmartDiacritics: false                 # replace "creme brulee" with "crème brûlée". Only available for de-DE and en-US languages
    smartDiacriticsLanguage:                  # de-DE or en-US: the replacement list to use when useSmartDiacritics is enabled

```

Each entry of `perLanguageSettings` applies to the pages written in that language (`language` must be one of the
languages declared in `system.languages.supported`). A regional code such as `fr-CA` falls back to the `fr` entry,
and any language without an entry uses the `default` one.

Notes:

- Code samples and scripts are never touched: `<code>`, `<pre>`, `<kbd>`, `<script>`, `<style>`, form controls... keep their content.
- One-letter words are glued to the next word (`a&nbsp;word`) unless `singleCharacterWordSpacing` is `disabled`, or `auto` with the French rules enabled (French typography keeps the plain space in "à votre service").
- Hyphenation uses the pattern file matching the page language (`fr`, `de`, `en` → `en-US`...). Languages without
  patterns are left unhyphenated and a warning is written to the Grav log.
- Invalid quote or dash style names (for example in a hand-written configuration file) fall back to the default
  style instead of breaking the page; a warning is written to the Grav log.
- A fragment whose HTML cannot be parsed is left untouched, with a warning in the Grav log.
- On a monolingual site the pages have no language: the `default` entry applies, including its French rules toggle.

Note that if you use the Admin Plugin, a file with your configuration named better-typography.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

## Usage

Once configured, Better Typography applies typographic improvements to the processed content of every page
(including modular sub-pages and collection items), based on the page language.

The plugin also provides a Twig filter `bettertypo` which can be used in your templates:

```twig
{{ page.header.title|bettertypo }}
```

You can pass an argument to the `bettertypo` Twig filter :

```twig
{{ page.header.title|bettertypo('default') }}
{{ page.header.title|bettertypo('fr') }}
```

The filter transforms HTML into HTML and its output is marked as *safe* for Twig's auto-escaping. When
auto-escaping is on (always on Grav 2), plain strings are HTML-escaped *before* the typography runs, so untrusted
input cannot inject markup; values that are already safe (`page.content`, `...|raw`) are processed as HTML. To
improve a string that intentionally contains HTML, mark it safe first: `{{ page.header.intro|raw|bettertypo }}`.

On Grav 2 the filter is also registered in the Twig content sandbox, so it can be used inside page content when
`process: { twig: true }` is enabled. Twig tags left in the content are protected while the page content is
processed, whatever the `twig_first` setting. A site can refuse the filter in content with
`security.twig_sandbox.denied_filters: [bettertypo]`.

## Development

```bash
composer install          # installs Rector, ECS and PHPStan
composer check            # rector --dry-run + ecs + phpstan (level 8)
composer rector           # apply Rector (PHP 8.2 sets, dead code, code quality, type declarations)
composer ecs              # fix coding style (PSR-12 + common sets)
composer vendor:release   # re-install vendor/ without dev dependencies before committing it
```

PHPStan runs without Grav (Grav core is not a Composer dependency of a plugin): `stubs/Plugin.stub` declares
the parent class `Grav\Common\Plugin` (PHPStan cannot analyse a class whose parent is unknown), and the
errors caused by the other missing Grav classes are listed in `phpstan-baseline.neon`. Regenerate it with
`vendor/bin/phpstan analyse --generate-baseline` after changing code that uses the Grav API.

The `vendor/` directory is committed (GPM installs the repository as-is) and must only contain the runtime
dependencies: run `composer vendor:release` before committing changes to it.

## Credits

The Better Typography plugin uses the [PHP-Typography library](https://github.com/mundschenk-at/php-typography) released under the GNU General Public License v2.0.
