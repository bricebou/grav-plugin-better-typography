<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Plugin;
use Grav\Plugin\BetterTypography\Typographer;
use InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use Stringable;
use Twig\TwigFilter;

/**
 * Applies typographic improvements (smart quotes, dashes, hyphenation, French spacing...) to the
 * processed page content and exposes them as the `bettertypo` Twig filter.
 */
class BetterTypographyPlugin extends Plugin
{
    public const string FILTER_NAME = 'bettertypo';

    private ?Typographer $typographer = null;

    /**
     * @return array<string, array<int, array{string, int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0],
            ],
        ];
    }

    /**
     * Composer autoload (called by Grav >= 1.7 before onPluginsInitialized).
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
        // Nothing to do in the admin (admin / admin2): pages are only rendered on the frontend.
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            // Run late (after shortcode-core) so shortcodes are already expanded.
            'onPageContentProcessed' => ['onPageContentProcessed', -20],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            // Grav 2 only: allow the filter inside sandboxed page content.
            'onBuildTwigSandboxPolicy' => ['onBuildTwigSandboxPolicy', 0],
        ]);
    }

    /**
     * Grav 2 runs Twig found in page content inside a sandbox with an allow-list of filters:
     * register `bettertypo` there so `{{ 'text'|bettertypo }}` works in content, not only in
     * theme templates. Site owners can still refuse it via `security.twig_sandbox.denied_filters`.
     */
    public function onBuildTwigSandboxPolicy(Event $event): void
    {
        $filters = $event['filters'] ?? [];
        if (! is_array($filters)) {
            return;
        }

        if (! in_array(self::FILTER_NAME, $filters, true)) {
            $filters[] = self::FILTER_NAME;
            $event['filters'] = $filters;
        }
    }

    public function onTwigInitialized(): void
    {
        // The filter returns HTML (e.g. <sup class="ordinal">), so its output is safe; with
        // auto-escaping on (always in Grav 2) plain strings are escaped *before* processing while
        // already-safe values (page.content, |raw) pass through: untrusted input cannot inject markup.
        $this->grav['twig']->twig()->addFilter(
            new TwigFilter(self::FILTER_NAME, $this->betterTypo(...), [
                'pre_escape' => 'html',
                'is_safe' => ['html'],
            ])
        );
    }

    /**
     * Improves the typography of the page whose content has just been processed
     * (main page, modular sub-pages and collection items alike).
     */
    public function onPageContentProcessed(Event $event): void
    {
        $page = $event['page'] ?? null;
        if (! $page instanceof PageInterface) {
            return;
        }

        $content = $page->getRawContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        $language = $page->language();
        $page->setRawContent($this->typographer()->process(
            $content,
            is_string($language) ? $language : $this->currentLanguage(),
            $this->processesTwigLater($page),
        ));
    }

    /**
     * Grav runs content Twig *after* this event unless `twig_first` is set: the Twig tags are still in
     * the content and must not be rewritten (smart quotes inside `{{ }}` break the template).
     */
    private function processesTwigLater(PageInterface $page): bool
    {
        if (! $page->shouldProcess('twig')) {
            return false;
        }

        $header = $page->header();
        $twigFirst = is_object($header) && property_exists($header, 'twig_first')
            ? $header->twig_first
            : $this->pluginConfig()
                ->get('system.pages.twig_first', false);

        return ! filter_var($twigFirst, FILTER_VALIDATE_BOOL);
    }

    /**
     * All supported languages set in System > Languages (used by blueprints.yaml).
     *
     * @return array<string, string>
     */
    public static function languageList(): array
    {
        $languages = [
            Typographer::DEFAULT_LANGUAGE => 'Default',
        ];

        foreach (self::supportedLanguages() as $language) {
            $languages[$language] = $language;
        }

        return $languages;
    }

    /**
     * Maximum number of entries of the per-language list (used by blueprints.yaml).
     */
    public static function maxLanguages(): int
    {
        return count(self::supportedLanguages()) + 1;
    }

    /**
     * `bettertypo` Twig filter.
     *
     * @param mixed       $string   The (trusted) HTML or text to improve.
     * @param string|null $language Language whose settings apply; defaults to the current page language.
     */
    public function betterTypo(mixed $string, ?string $language = null): string
    {
        if ($string === null) {
            return '';
        }

        if (! is_scalar($string) && ! $string instanceof Stringable) {
            throw new InvalidArgumentException(sprintf('The "bettertypo" filter expects a string, %s given.', get_debug_type($string)));
        }

        return $this->typographer()
            ->process((string) $string, $language ?? $this->currentLanguage());
    }

    private function typographer(): Typographer
    {
        if (! $this->typographer instanceof Typographer) {
            $settings = $this->pluginConfig()
                ->get('plugins.better-typography.perLanguageSettings');
            $logger = $this->grav['log'] ?? null;
            $warn = is_object($logger) && method_exists($logger, 'warning')
                ? static function (string $message) use ($logger): void {
                    $logger->warning($message);
                }
            : null;

            $this->typographer = new Typographer(is_iterable($settings) ? $settings : [], $warn);
        }

        return $this->typographer;
    }

    /**
     * Language of the current page, else the active/default site language, else null ("default" settings).
     */
    private function currentLanguage(): ?string
    {
        $page = $this->grav['page'] ?? null;
        $language = $page instanceof PageInterface ? $page->language() : null;

        if (! is_string($language) || $language === '') {
            $language = $this->grav['language']->getLanguage();
        }

        if (! is_string($language) || $language === '') {
            $language = $this->pluginConfig()
                ->get('system.languages.default_lang');
        }

        return is_string($language) && $language !== '' ? $language : null;
    }

    private function pluginConfig(): Config
    {
        $config = $this->config ?? Grav::instance()['config'];
        if (! $config instanceof Config) {
            throw new RuntimeException('Grav configuration is not available.');
        }

        return $config;
    }

    /**
     * @return array<int, string>
     */
    private static function supportedLanguages(): array
    {
        $supported = Grav::instance()['config']->get('system.languages.supported');

        return array_values(array_filter((array) $supported, is_string(...)));
    }
}
