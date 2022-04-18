<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
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
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
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
    * betterTypo
    *
    * @param  string $string
    * @param  string (optional) $language
    * @return string
    */
    public function betterTypo(string $string, string $language = null): string
    {
        $settings = new Settings(true);

        if (!$language) {
            $language = $this->grav['language']->getLanguage();
        }

        $useSmartQuotes = $this->config->get('plugins.better-typography.useSmartQuotes', true);
        $settings->set_smart_quotes($useSmartQuotes);

        if ($useSmartQuotes) {
            $settings->set_smart_quotes_primary($this->config->get('plugins.better-typography.smartQuotesStyle', 'doubleCurled'));
            $settings->set_smart_quotes_secondary($this->config->get('plugins.better-typography.smartQuotesStyleSecondary', 'singleCurled'));
        }

        $useSmartDashes = $this->config->get('plugins.better-typography.useSmartDashes', true);
        $settings->set_smart_dashes($useSmartDashes);

        if ($useSmartDashes) {
            $settings->set_smart_dashes_style($this->config->get('plugins.better-typography.dashStyle', 'international'));
        }

        $applyHyphenations = $this->config->get('plugins.better-typography.applyHyphenations', false);
        $settings->set_hyphenation($applyHyphenations);

        if ($applyHyphenations) {
            $settings->set_hyphenation_language($language);
        }

        $applyFrenchSpecific = $this->config->get('plugins.better-typography.applyFrenchSpecific', false);
        if ($applyFrenchSpecific && $language === 'fr') {
            $settings->set_french_punctuation_spacing(true);
            $settings->set_smart_ordinal_suffix_match_roman_numerals(true);
            $settings->set_true_no_break_narrow_space(true);
        }

        $useSmartDiacritics = $this->config->get('plugins.better-typography.useSmartDiacritics', false);

        if ($useSmartDiacritics && $language === $this->config->get('plugins.better-typography.useSmartDiacritics')) {
            $settings->set_smart_diacritics(true);
            $settings->set_diacritic_language($language);
        }

        $typography = new PHP_Typography();

        return $typography->process($string, $settings);
    }
}
