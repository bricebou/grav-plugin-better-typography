<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use PHP_Typography\PHP_Typography;
use PHP_Typography\Settings;
use Twig_SimpleFilter;

/**
* Class BetterTypographyPlugin
* @package Grav\Plugin
*/
class BetterTypographyPlugin extends Plugin
{
    /**
    * @return array
    *
    * The getSubscribedEvents() gives the core a list of events
    *     that the plugin wants to listen to. The key of each
    *     array section is the event that the plugin listens to
    *     and the value (in the form of an array) contains the
    *     callable (or function) as well as the priority. The
    *     higher the number the higher the priority.
    */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                // Uncomment following line when plugin requires Grav < 1.7
                // ['autoload', 100000],
                ['onPluginsInitialized', 0]
                ]
        ];
    }

    /**
    * Composer autoload
    *
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
    * Initialize the plugin
    */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
        // Enable the main events we are interested in
        $this->enable([
            // Put your main events here
            'onPageContentProcessed' => ['onPageContentProcessed', -20],
            'onTwigInitialized' => ['onTwigInitialized', 0]
        ]);
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFilter(
            new Twig_SimpleFilter('bettertypo', [$this, 'betterTypo'])
        );
    }

    public function onPageContentProcessed(): void
    {
        $content = $this->grav['page']->content();
        $content = $this->betterTypo($content);
        $this->grav['page']->setRawContent($content);
    }

    /**
     * languageList
     *
     * get all supported languages set in System / Languages
     *
     * @return array
     */
    public static function languageList(): array
    {
        /** @var Grav */
        $grav = Grav::instance();
        /** @var Data */
        $config = $grav['config'];

        $languages = [
            'default' => 'Default'
        ];

        foreach ($config->get('system.languages.supported', []) as $language) {
            $languages[$language] = $language;
        }

        return $languages;
    }

    /**
     * maxLanguages
     *
     * @return int
     */
    public static function maxLanguages(): int
    {
        /** @var Grav */
        $grav = Grav::instance();
        /** @var Data */
        $config = $grav['config'];

        return count($config->get('system.languages.supported', [])) + 1;
    }


    /**
    * betterTypo
    *
    * @param  string $string
    * @param  string (optional) $language
    * @return string
    */
    public function betterTypo(string $string, string $language = null): string
    {
        $PHPTypoSettings = new Settings(false);
        $PHPTypoSettings->set_smart_ordinal_suffix(true);
        $PHPTypoSettings->set_smart_ellipses(true);
        $PHPTypoSettings->set_smart_marks(true);
        $PHPTypoSettings->set_smart_exponents(true);
        $PHPTypoSettings->set_smart_fractions(true);
        $PHPTypoSettings->set_smart_area_units(true);
        $PHPTypoSettings->set_single_character_word_spacing(true);
        $PHPTypoSettings->set_fraction_spacing(true);
        $PHPTypoSettings->set_unit_spacing(true);
        $PHPTypoSettings->set_numbered_abbreviation_spacing(true);
        $PHPTypoSettings->set_dewidow(true);

        if (!$language) {
            $language = $this->grav['page']->language() ?? $this->grav['language']->getLanguage();
            if (!$language) {
                $language = $this->grav['config']->get('site.default_lang');
            }
        }

        $betterTypoSettings = [];

        foreach ($this->config->get('plugins.better-typography.perLanguageSettings', []) as $perLanguageSettings) {
            $betterTypoSettings[$perLanguageSettings['language']] = $perLanguageSettings;
        }

        $betterTypoLanguage = array_key_exists($language, $betterTypoSettings) ? $language : 'default';

        $useSmartQuotes = $betterTypoSettings[$betterTypoLanguage]['useSmartQuotes'] ?? true;
        $PHPTypoSettings->set_smart_quotes($useSmartQuotes);
        if ($useSmartQuotes) {
            $smartQuotesPrimary = $betterTypoSettings[$betterTypoLanguage]['smartQuotesStyle'] ?? 'doubleCurled';
            $PHPTypoSettings->set_smart_quotes_primary($smartQuotesPrimary);
            $smartQuotesSecondary = $betterTypoSettings[$betterTypoLanguage]['smartQuotesStyleSecondary'] ?? 'singleCurled';
            $PHPTypoSettings->set_smart_quotes_secondary($smartQuotesSecondary);
        }

        $useSmartDashes = $betterTypoSettings[$betterTypoLanguage]['useSmartDashes'] ?? true;
        $PHPTypoSettings->set_smart_dashes($useSmartDashes);
        if ($useSmartDashes) {
            $smartDashesStyle = $betterTypoSettings[$betterTypoLanguage]['smartDashesStyle'] ?? 'international';
            $PHPTypoSettings->set_smart_dashes_style($smartDashesStyle);
        }

        $applyHyphenations = $betterTypoSettings[$betterTypoLanguage]['applyHyphenations'] ?? false;
        $PHPTypoSettings->set_hyphenation($applyHyphenations);
        if ($applyHyphenations) {
            $PHPTypoSettings->set_hyphenation_language($language);
        }

        $applyFrenchSpecific = $betterTypoSettings[$betterTypoLanguage]['applyFrenchSpecific'] ?? false;
        if ($applyFrenchSpecific && $language === 'fr') {
            $PHPTypoSettings->set_french_punctuation_spacing(true);
            $PHPTypoSettings->set_smart_ordinal_suffix_match_roman_numerals(true);
        }

        $useSmartDiacritics = $betterTypoSettings[$betterTypoLanguage]['useSmartDiacritics'] ?? false;
        if ($useSmartDiacritics && $language === $betterTypoSettings[$betterTypoLanguage]['smartDiacriticsLanguage']) {
            $PHPTypoSettings->set_smart_diacritics(true);
            $PHPTypoSettings->set_diacritic_language($language);
        }


        $PHPTypo = new PHP_Typography();

        return $PHPTypo->process($string, $PHPTypoSettings);
    }
}
