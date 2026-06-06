<?php

declare(strict_types=1);

namespace Switon\I18n\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\I18n\LocaleContext;

/**
 * Test cases for LocaleContext class.
 *
 * Tests the context class that stores locale information.
 */
class LocaleContextTest extends BaseTestCase
{
    /**
     * Test that LocaleContext can be instantiated.
     *
     * Verifies that LocaleContext can be created without errors.
     */
    public function testLocaleContextCanBeInstantiated(): void
    {
        // Act
        $context = new LocaleContext();

        // Assert
        $this->assertInstanceOf(LocaleContext::class, $context, 'LocaleContext should be instantiable');
    }

    /**
     * Test that locale property can be set and retrieved.
     *
     * Verifies that the locale property can be set and retrieved
     * from the LocaleContext instance.
     */
    public function testLocalePropertyCanBeSetAndRetrieved(): void
    {
        // Arrange
        $context = new LocaleContext();

        // Act
        $context->locale = 'zh-cn';
        $result = $context->locale;

        // Assert
        $this->assertSame('zh-cn', $result, 'locale property should be set and retrieved correctly');
    }

    /**
     * Test that locale property can be updated.
     *
     * Verifies that the locale property can be updated multiple times.
     */
    public function testLocalePropertyCanBeUpdated(): void
    {
        // Arrange
        $context = new LocaleContext();

        // Act
        $context->locale = 'en';
        $result1 = $context->locale;

        $context->locale = 'fr';
        $result2 = $context->locale;

        // Assert
        $this->assertSame('en', $result1, 'First locale value should be stored');
        $this->assertSame('fr', $result2, 'Second locale value should replace first');
    }

    /**
     * Test that locale property is initially unset.
     *
     * Verifies that the locale property is not set by default
     * and can be checked with isset().
     */
    public function testLocalePropertyIsInitiallyUnset(): void
    {
        // Arrange
        $context = new LocaleContext();

        // Act & Assert
        $this->assertFalse(isset($context->locale), 'locale property should not be set initially');
    }
}
