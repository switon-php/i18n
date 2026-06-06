<?php

declare(strict_types=1);

namespace Switon\I18n\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\I18n\Event\TranslationKeyMissing;
use ReflectionClass;

/**
 * Tests the missing-translation event payload.
 */
class TranslationKeyMissingTest extends TestCase
{
    /**
     * Test that event stores key and effective locale.
     */
    public function testEventStoresKeyAndLocale(): void
    {
        // Act
        $event = new TranslationKeyMissing(key: 'user.missing', locale: 'zh-cn');

        // Assert
        $this->assertSame('user.missing', $event->key);
        $this->assertSame('zh-cn', $event->locale);
    }

    /**
     * Test that jsonSerialize() returns key and locale payload.
     */
    public function testJsonSerializeReturnsKeyAndLocale(): void
    {
        // Arrange
        $event = new TranslationKeyMissing(key: 'user.missing', locale: 'en');

        // Act
        $payload = $event->jsonSerialize();

        // Assert
        $this->assertSame([
            'key' => 'user.missing',
            'locale' => 'en',
        ], $payload);
    }

    /**
     * Test that event keeps INFO level metadata for observability.
     */
    public function testEventKeepsInfoLevelAttribute(): void
    {
        // Arrange
        $reflection = new ReflectionClass(TranslationKeyMissing::class);
        $attributes = $reflection->getAttributes(EventLevel::class);

        // Act
        $attribute = $attributes[0]?->newInstance();

        // Assert
        $this->assertNotNull($attribute);
        $this->assertSame(Severity::INFO, $attribute->severity);
    }
}
