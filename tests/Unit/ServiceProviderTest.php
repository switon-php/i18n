<?php

declare(strict_types=1);

namespace Switon\I18n\Tests\Unit;

use Switon\Core\ContainerInterface;
use Switon\Core\LocaleInterface;
use Switon\Core\TranslatorInterface;
use Switon\I18n\Locale;
use Switon\I18n\ServiceProvider;
use Switon\I18n\Tests\TestCase;
use Switon\I18n\Translator;

class ServiceProviderTest extends TestCase
{
    public function testRegisterBindsLocaleInterfaceToLocale(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $id, mixed $definition) use ($container): ContainerInterface {
                if ($id === LocaleInterface::class) {
                    $this->assertSame(Locale::class, $definition);
                } elseif ($id === TranslatorInterface::class) {
                    $this->assertSame(Translator::class, $definition);
                } else {
                    $this->fail('Unexpected binding: ' . $id);
                }

                return $container;
            });

        $provider->register($container);
    }

    public function testBootIsNoopAndDoesNotThrow(): void
    {
        $provider = new ServiceProvider();

        $provider->boot();

        $this->addToAssertionCount(1);
    }
}
