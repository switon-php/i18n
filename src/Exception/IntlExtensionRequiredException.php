<?php

declare(strict_types=1);

namespace Switon\I18n\Exception;

use Switon\I18n\Exception as BaseException;

/**
 * Exception for ICU translation requests when ext-intl is unavailable.
 *
 * @see \Switon\I18n\Translator
 */
class IntlExtensionRequiredException extends BaseException
{
}
