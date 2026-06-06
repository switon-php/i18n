<?php

declare(strict_types=1);

namespace Switon\I18n;

use Switon\Core\ContainerInterface;
use Switon\Core\LocaleInterface;
use Switon\Core\ServiceProviderInterface;
use Switon\Core\TranslatorInterface;

/**
 * Registers i18n services used to resolve the current locale and translations.
 *
 * Road-signs:
 * - LocaleInterface→Locale
 * - TranslatorInterface→Translator
 *
 * @see \Switon\Core\ServiceProviderInterface
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers locale and translator bindings for container consumers.
     */
    public function register(ContainerInterface $container): void
    {
        $container->set(LocaleInterface::class, Locale::class);
        $container->set(TranslatorInterface::class, Translator::class);
    }

    /**
     * No-op hook kept for service provider lifecycle parity.
     */
    public function boot(): void
    {
    }
}
