# Better Typography Plugin

The **Better Typography** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav).
It automatically improves the typography of your content and provides a Twig filter.

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
    useSmartDiacritics: false                 # replace "creme brulee" with "crème brûlée". Only available for de-DE and en-US languages
    smartDiacriticsLanguage:                  # de-DE or en-US

```

Note that if you use the Admin Plugin, a file with your configuration named better-typography.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

## Usage

Once configured, the Better Typography will apply typographic improvements onto yout content, based on its language.

The Better TYpography plugin also provides a Twig filter `bettertypo` which can be used in your skeletons:

```twig
{{ page.header.title|bettertypo }}
```

You can pass an argument to the `bettertypo` Twig filter :

```twig
{{ page.header.title|bettertypo('default') }}
{{ page.header.title|bettertypo('fr') }}
```

## Credits

The Better Typography plugin uses the [PHP-Typography library](https://github.com/mundschenk-at/php-typography) released under the GNU General Public License v2.0.
