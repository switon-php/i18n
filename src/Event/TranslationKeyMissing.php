<?php

declare(strict_types=1);

namespace Switon\I18n\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted when translation lookup falls back because no locale candidate contains the key.
 *
 * Log category: <code>switon.i18n.translation.key.missing</code>
 *
 * @see \Switon\I18n\Translator
 */
#[EventLevel(Severity::INFO)]
class TranslationKeyMissing implements JsonSerializable
{
    /**
     * @param string $key Translation key returned to the caller as the fallback value.
     * @param string $locale Effective locale used for the failed lookup.
     */
    public function __construct(
        public string $key,
        public string $locale,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'locale' => $this->locale,
        ];
    }
}
