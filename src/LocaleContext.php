<?php

declare(strict_types=1);

namespace Switon\I18n;

/**
 * Carries request-local locale state for the i18n component.
 *
 * @see \Switon\I18n\Locale
 * @see \Switon\Core\ContextManagerInterface
 */
class LocaleContext
{
    /** Effective locale code for the current request context. */
    public string $locale;
}
