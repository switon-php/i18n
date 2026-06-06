<?php

declare(strict_types=1);

namespace Switon\I18n;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Core\LocaleInterface as CoreLocaleInterface;

use function str_replace;
use function strtolower;
use function trim;

/**
 * Stores and resolves request-local locale values through context isolation.
 *
 * Use this as the mutable locale facade for request-local language state.
 *
 * @see \Switon\Core\LocaleInterface
 * @see \Switon\I18n\LocaleContext
 */
class Locale implements CoreLocaleInterface, ContextAware
{
    #[Autowired] protected ContextManagerInterface $contextManager;

    /** Default locale returned when no request-local locale has been set. */
    #[Autowired] protected string $default = 'en';

    /**
     * Return locale context for the current request.
     */
    public function getContext(): LocaleContext
    {
        return $this->contextManager->getContext($this);
    }

    /**
     * Return the current request locale or configured default locale.
     */
    public function get(): string
    {
        return $this->getContext()->locale ?? $this->getDefault();
    }

    /**
     * Set locale for the current request context.
     *
     * @param string $locale Locale code
     *
     * @return static Returns self for method chaining
     */
    public function set(string $locale): static
    {
        $context = $this->getContext();

        $context->locale = $this->normalizeLocale($locale);

        return $this;
    }

    /**
     * Return the configured default locale.
     */
    public function getDefault(): string
    {
        return $this->normalizeLocale($this->default);
    }

    /**
     * Normalize locale input to the component's stored form.
     */
    protected function normalizeLocale(string $locale): string
    {
        return str_replace('_', '-', strtolower(trim($locale)));
    }
}
