<?php

declare(strict_types=1);

namespace Switon\I18n\Exception;

use Switon\I18n\Exception as BaseException;

/**
 * Raised when a locale file does not return flat string translations.
 *
 * @see \Switon\I18n\Translator
 */
class InvalidLocaleFileException extends BaseException
{
}
