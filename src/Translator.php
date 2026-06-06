<?php

declare(strict_types=1);

namespace Switon\I18n;

use DateTimeInterface;
use MessageFormatter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\Json;
use Switon\Core\LocaleInterface;
use Switon\Core\TranslatorInterface;
use Switon\I18n\Event\TranslationKeyMissing;
use Switon\I18n\Exception\IntlExtensionRequiredException;
use Switon\I18n\Exception\InvalidLocaleFileException;
use Switon\I18n\Exception\LocaleFileNotFoundException;

use function explode;
use function get_debug_type;
use function is_array;
use function pathinfo;
use function str_contains;
use function str_replace;
use function strtolower;
use function strtr;
use function trim;

/**
 * Implements locale-aware key translation with lazy template loading.
 *
 * Use when translation files are grouped by locale and messages should resolve
 * through fallback candidates with placeholder interpolation.
 *
 * Configure with:
 * - resource paths: <code>dirs</code>
 * - locale-specific override chain: <code>fallbacks</code>
 *
 * Guidance: Missing keys dispatch <code>TranslationKeyMissing</code> and then fall back to the key itself.
 *
 * @see \Switon\Core\TranslatorInterface
 * @see \Switon\I18n\Exception
 * @see \Switon\I18n\Exception\InvalidLocaleFileException
 * @see \Switon\Core\LocaleInterface
 * @see \Switon\I18n\Event\TranslationKeyMissing
 * @see \Switon\I18n\Exception\LocaleFileNotFoundException
 * @see \Switon\Core\FilesystemInterface
 */
class Translator implements TranslatorInterface
{
    #[Autowired] protected LocaleInterface $locale;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    /** @var list<string> Translation directories in merge order. */
    #[Autowired] protected array $dirs = ['@resources/i18n'];

    /** @var array<string, list<string>> Extra fallback locales keyed by requested locale. */
    #[Autowired] protected array $fallbacks = [];

    /** @var array<string, list<string>> Map of locale to ordered translation file paths. */
    protected array $files = [];

    /** @var array<string, array<string, string>> Loaded templates by locale. */
    protected array $templates = [];

    /**
     * Discover available locale files in the configured translation directories.
     */
    public function __construct()
    {
        foreach ($this->dirs as $dir) {
            foreach ($this->filesystem->glob($dir . '/*.php') as $file) {
                $locale = strtolower(pathinfo($file, PATHINFO_FILENAME));
                $this->files[$locale] ??= [];
                $this->files[$locale][] = $file;
            }
        }
    }

    /**
     * Translate one translation key using locale fallback and placeholder replacement.
     *
     * @param string $id Translation key
     * @param array<string, mixed> $bind Placeholder values to replace in the message
     * @param bool $useICU Whether to use ICU MessageFormat
     *
     * @throws \Switon\I18n\Exception\InvalidLocaleFileException When a locale file does not return flat string translations
     * @throws \Switon\I18n\Exception\LocaleFileNotFoundException When no candidate locale file exists
     * @throws \Switon\I18n\Exception\IntlExtensionRequiredException When ICU mode is enabled without intl extension
     */
    public function translate(string $id, array $bind = [], bool $useICU = false): string
    {
        // 1. Get message template for this key
        $message = $this->getTemplate($id);

        // 2. If using ICU MessageFormat
        if ($useICU) {
            if (!extension_loaded('intl')) {
                IntlExtensionRequiredException::raise('intl extension is required when {option} is true', [
                    'option' => '$useICU',
                ]);
            }
            // MessageFormatter expects scalar (or DateTimeInterface) values; normalize array/object to JSON string
            $normalized = [];
            foreach ($bind as $k => $v) {
                $normalized[$k] = (is_scalar($v) || $v instanceof DateTimeInterface) ? $v : Json::stringify($v);
            }
            $result = MessageFormatter::formatMessage($this->locale->get(), $message, $normalized);
            // MessageFormatter returns false on invalid syntax
            return $result !== false ? $result : $message;
        }

        // 3. Simple placeholder replacement
        return $this->replacePlaceholders($message, $bind);
    }

    /**
     * Resolve one translation key through locale fallback candidates.
     *
     * @param string $id Translation key
     *
     * @return string Message template or the key itself when not found
     *
     * @throws \Switon\I18n\Exception\InvalidLocaleFileException When a locale file does not return flat string translations
     * @throws \Switon\I18n\Exception\LocaleFileNotFoundException When no candidate locale file exists
     */
    protected function getTemplate(string $id): string
    {
        $locale = $this->locale->get();
        $candidates = $this->getFallbackCandidates($locale);

        // Load all candidate locale files (if not already loaded)
        $hasAnyFile = false;
        foreach ($candidates as $candidate) {
            if (!isset($this->templates[$candidate])) {
                $files = $this->files[$candidate] ?? [];
                if ($files !== []) {
                    $this->templates[$candidate] = [];
                    foreach ($files as $file) {
                        foreach ($this->loadLocaleFile($candidate, $file) as $key => $message) {
                            $this->templates[$candidate][$key] = $message;
                        }
                    }
                    $hasAnyFile = true;
                } else {
                    // Mark as checked but not found
                    $this->templates[$candidate] = [];
                }
            } elseif (!empty($this->templates[$candidate])) {
                $hasAnyFile = true;
            }
        }

        // If no locale file exists for any candidate, throw exception
        if (!$hasAnyFile) {
            LocaleFileNotFoundException::raise('Locale file not found: {locale}', ['locale' => $locale]);
        }

        // Search for key in configured fallback order for this locale.
        foreach ($candidates as $candidate) {
            if (isset($this->templates[$candidate][$id])) {
                return $this->templates[$candidate][$id];
            }
        }

        // If key not found in any locale, dispatch event and return key itself
        $this->eventDispatcher->dispatch(new TranslationKeyMissing(key: $id, locale: $locale));
        return $id;
    }

    /**
     * Check whether a translation key exists in the current locale fallback chain.
     */
    public function has(string $id): bool
    {
        $locale = $this->locale->get();
        $candidates = $this->getFallbackCandidates($locale);

        foreach ($candidates as $candidate) {
            if (!isset($this->templates[$candidate])) {
                $files = $this->files[$candidate] ?? [];
                if ($files !== []) {
                    $this->templates[$candidate] = [];
                    foreach ($files as $file) {
                        foreach ($this->loadLocaleFile($candidate, $file) as $key => $message) {
                            $this->templates[$candidate][$key] = $message;
                        }
                    }
                } else {
                    $this->templates[$candidate] = [];
                }
            }

            if (isset($this->templates[$candidate][$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load one locale file and validate the returned translation map.
     *
     * @return array<string, string>
     */
    protected function loadLocaleFile(string $locale, string $file): array
    {
        $translations = require $file;

        if (!is_array($translations)) {
            InvalidLocaleFileException::raise(
                'Locale file {file} for {locale} must return array<string, string>, got {type}',
                [
                    'file' => $file,
                    'locale' => $locale,
                    'type' => get_debug_type($translations),
                ]
            );
        }

        foreach ($translations as $key => $message) {
            if (!is_string($key) || !is_string($message)) {
                InvalidLocaleFileException::raise(
                    'Locale file {file} for {locale} must contain string keys and string messages; invalid entry {key}',
                    [
                        'file' => $file,
                        'locale' => $locale,
                        'key' => (string)$key,
                    ]
                );
            }
        }

        /** @var array<string, string> $translations */
        return $translations;
    }

    /**
     * Replace <code>{key}</code> placeholders in one message template.
     *
     * @param string $message Message template with {key} placeholders
     * @param array<string, mixed> $placeholders Placeholder values
     */
    protected function replacePlaceholders(string $message, array $placeholders): string
    {
        if (empty($placeholders)) {
            return $message;
        }

        $replaces = [];
        foreach ($placeholders as $k => $v) {
            $replaces['{' . $k . '}'] = is_string($v) ? $v : Json::stringify($v);
        }

        return strtr($message, $replaces);
    }

    /**
     * Return fallback locale candidates in lookup order.
     *
     * Order: requested locale -> configured fallbacks -> language-only locale -> default locale.
     *
     * @param string $locale Requested locale
     *
     * @return list<string> Candidate locales to try
     */
    protected function getFallbackCandidates(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);
        $candidates = [$locale];

        foreach ($this->getConfiguredFallbacks($locale) as $fallback) {
            if (!in_array($fallback, $candidates, true)) {
                $candidates[] = $fallback;
            }
        }

        if (str_contains($locale, '-')) {
            $language = explode('-', $locale, 2)[0];
            if ($language !== $locale && !in_array($language, $candidates, true)) {
                $candidates[] = $language;
            }
        }

        $default = $this->normalizeLocale($this->locale->getDefault());
        if (!in_array($default, $candidates, true)) {
            $candidates[] = $default;
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    protected function getConfiguredFallbacks(string $locale): array
    {
        foreach ($this->fallbacks as $source => $targets) {
            if ($this->normalizeLocale($source) !== $locale || !is_array($targets)) {
                continue;
            }

            $normalized = [];
            foreach ($targets as $target) {
                if (!is_string($target)) {
                    continue;
                }

                $target = $this->normalizeLocale($target);
                if ($target !== '' && !in_array($target, $normalized, true)) {
                    $normalized[] = $target;
                }
            }

            return $normalized;
        }

        return [];
    }

    /**
     * Normalize locale identifiers for lookup and fallback comparisons.
     */
    protected function normalizeLocale(string $locale): string
    {
        return str_replace('_', '-', strtolower(trim($locale)));
    }
}
