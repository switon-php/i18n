# Switon I18n Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/i18n/ci.yml?branch=main&label=CI)](https://github.com/switon-php/i18n/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's locale-aware translation layer for request-local locale state, fallback lookup, and message formatting.

## Highlights

- **One translation entrypoint:** `TranslatorInterface` gives app services one place for message lookup.
- **Request-local locale:** the active locale stays isolated per request.
- **Fallback-aware lookup:** translation files can fall back through configured candidates.
- **Formatted messages:** placeholders and ICU formatting both work from the same source.
- **Missing-key reporting:** `TranslationKeyMissing` can surface which translations are still missing.

## Installation

```bash
composer require switon/i18n
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Core\LocaleInterface;
use Switon\Core\TranslatorInterface;

class WelcomeService
{
    #[Autowired] protected TranslatorInterface $translator;
    #[Autowired] protected LocaleInterface $locale;

    public function message(string $name): string
    {
        $this->locale->set('en');

        return $this->translator->translate('hello', ['name' => $name]);
    }
}
```

Docs: https://docs.switon.dev/latest/i18n

## License

MIT.
