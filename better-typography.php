<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

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
            new \Twig_SimpleFilter('bettertypo', [$this, 'betterTypo'])
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
        $config = $this->config();
        $settings = new \PHP_Typography\Settings(true);

        if (!empty($config['smart_dashes'])) {
            $settings->set_smart_dashes(true);
            $settings->set_smart_dashes_style($config['dash_style']);
        } else {
            $settings->set_smart_dashes(false);
        }

        if (!empty($config['smart_quotes'])) {
            $settings->set_smart_quotes(true);
            $settings->set_smart_quotes_primary($config['quote_style']);
        } else {
            $settings->set_smart_quotes(false);
        }

        if (!$language) {
            $language = $this->grav['language']->getLanguage();
        }

        if ($language === 'fr' && !empty($config['french_specific'])) {
            $settings->set_french_punctuation_spacing(true);
            $settings->set_smart_ordinal_suffix_match_roman_numerals(true);
        }

        if (!empty($config['hyphenations'])) {
            $settings->set_hyphenation(true);
            $settings->set_hyphenation_language($language);
        } else {
            $settings->set_hyphenation(false);
        }

        if (!empty($config['diacritics'])) {
            $settings->set_smart_diacritics(true);
            $settings->set_diacritic_language($language);
        } else {
            $settings->set_smart_diacritics(false);
        }

        $typography = new \PHP_Typography\PHP_Typography();

        return $typography->process($string, $settings);
    }
}
