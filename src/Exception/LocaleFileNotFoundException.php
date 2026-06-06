<?php

declare(strict_types=1);

namespace Switon\I18n\Exception;

use Switon\I18n\Exception as BaseException;

/**
 * Exception for missing locale translation files.
 *
 * Thrown when no translation file exists for requested locale candidates.
 *
 * @see \Switon\I18n\Exception
 * @see \Switon\I18n\Translator
 */
class LocaleFileNotFoundException extends BaseException
{
}
