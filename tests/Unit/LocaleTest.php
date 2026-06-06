<?php

declare(strict_types=1);

namespace Switon\I18n\Tests\Unit;

use Switon\Core\LocaleInterface;
use Switon\I18n\Locale;
use Switon\I18n\LocaleContext;
use Switon\I18n\Tests\TestCase;

/**
 * Test cases for Locale class.
 *
 * Tests locale management: get(), set(), getDefault(), and context handling.
 */
class LocaleTest extends TestCase
{
    protected Locale $locale;

    protected function setUp(): void
    {
        parent::setUp();

        // Register Locale with default configuration
        $this->container->set(LocaleInterface::class, [
            'class' => Locale::class,
            'default' => 'en',
        ]);

        // Get Locale instance (Container will auto-inject dependencies)
        $this->locale = $this->container->get(LocaleInterface::class);
    }

    /**
     * Test that get() returns default locale when context is not set.
     *
     * Verifies that get() returns the default locale value when
     * no locale has been set in the context.
     */
    public function testGetReturnsDefaultLocaleWhenContextNotSet(): void
    {
        // Act
        $result = $this->locale->get();

        // Assert
        $this->assertSame('en', $result, 'get() should return default locale when context is not set');
    }

    /**
     * Test that set() stores locale in context and get() retrieves it.
     *
     * Verifies that set() stores the locale value in the context
     * and get() retrieves it correctly.
     */
    public function testSetStoresLocaleInContextAndGetRetrievesIt(): void
    {
        // Act
        $this->locale->set('zh-cn');
        $result = $this->locale->get();

        // Assert
        $this->assertSame('zh-cn', $result, 'get() should return the locale set by set()');
    }

    /**
     * Test that set() normalizes locale to lowercase.
     *
     * Verifies that set() converts locale to lowercase before storing.
     */
    public function testSetNormalizesLocaleToLowercase(): void
    {
        // Act
        $this->locale->set('ZH-CN');
        $result = $this->locale->get();

        // Assert
        $this->assertSame('zh-cn', $result, 'set() should normalize locale to lowercase');
    }

    /**
     * Test that set() normalizes separators and trims whitespace.
     *
     * Verifies that set() stores locale values in the same canonical form
     * used by request-driven locale detection.
     */
    public function testSetNormalizesLocaleSeparatorsAndWhitespace(): void
    {
        // Act
        $this->locale->set(' ZH_CN ');
        $result = $this->locale->get();

        // Assert
        $this->assertSame('zh-cn', $result, 'set() should trim whitespace and normalize separators');
    }

    /**
     * Test that set() returns self for method chaining.
     *
     * Verifies that set() returns the Locale instance to allow method chaining.
     */
    public function testSetReturnsSelfForMethodChaining(): void
    {
        // Act
        $result = $this->locale->set('fr');

        // Assert
        $this->assertSame($this->locale, $result, 'set() should return self for method chaining');
    }

    /**
     * Test that getDefault() returns the configured default locale.
     *
     * Verifies that getDefault() returns the default locale value
     * that was configured via dependency injection.
     */
    public function testGetDefaultReturnsConfiguredDefaultLocale(): void
    {
        // Act
        $result = $this->locale->getDefault();

        // Assert
        $this->assertSame('en', $result, 'getDefault() should return the configured default locale');
    }

    /**
     * Test that configured default locale is normalized on read.
     *
     * Verifies that default locale uses the same canonical form as manual
     * and request-driven locale values.
     */
    public function testGetDefaultNormalizesConfiguredLocale(): void
    {
        // Arrange
        $this->container->remove(LocaleInterface::class);
        $this->container->set(LocaleInterface::class, [
            'class' => Locale::class,
            'default' => ' EN_US ',
        ]);

        $locale = $this->container->get(LocaleInterface::class);

        // Act
        $default = $locale->getDefault();
        $current = $locale->get();

        // Assert
        $this->assertSame('en-us', $default, 'getDefault() should normalize configured default locale');
        $this->assertSame('en-us', $current, 'get() should use normalized default locale when context is empty');
    }

    /**
     * Test that getContext() returns LocaleContext instance.
     *
     * Verifies that getContext() returns a LocaleContext instance
     * that can be used to access the locale value directly.
     */
    public function testGetContextReturnsLocaleContextInstance(): void
    {
        // Act
        $context = $this->locale->getContext();

        // Assert
        $this->assertInstanceOf(LocaleContext::class, $context, 'getContext() should return LocaleContext instance');
    }

    /**
     * Test that locale persists across multiple get() calls.
     *
     * Verifies that once a locale is set, it persists in the context
     * and can be retrieved multiple times.
     */
    public function testLocalePersistsAcrossMultipleGetCalls(): void
    {
        // Arrange
        $this->locale->set('ja');

        // Act
        $result1 = $this->locale->get();
        $result2 = $this->locale->get();

        // Assert
        $this->assertSame('ja', $result1, 'First get() call should return set locale');
        $this->assertSame('ja', $result2, 'Second get() call should return same locale');
        $this->assertSame($result1, $result2, 'Multiple get() calls should return same locale');
    }

    /**
     * Test that set() can update locale multiple times.
     *
     * Verifies that set() can be called multiple times to update
     * the locale value, and get() always returns the latest value.
     */
    public function testSetCanUpdateLocaleMultipleTimes(): void
    {
        // Act
        $this->locale->set('en');
        $result1 = $this->locale->get();

        $this->locale->set('zh-cn');
        $result2 = $this->locale->get();

        $this->locale->set('fr');
        $result3 = $this->locale->get();

        // Assert
        $this->assertSame('en', $result1, 'First locale should be stored');
        $this->assertSame('zh-cn', $result2, 'Second locale should replace first');
        $this->assertSame('fr', $result3, 'Third locale should replace second');
    }

    /**
     * Test that locale with region code is handled correctly.
     *
     * Verifies that locale codes with region (e.g., 'zh-cn', 'en-us')
     * are stored and retrieved correctly.
     */
    public function testLocaleWithRegionCodeIsHandledCorrectly(): void
    {
        // Act
        $this->locale->set('zh-cn');
        $result = $this->locale->get();

        // Assert
        $this->assertSame('zh-cn', $result, 'Locale with region code should be stored correctly');
    }

    /**
     * Test that simple locale code (without region) is handled correctly.
     *
     * Verifies that simple locale codes (e.g., 'en', 'zh', 'fr')
     * are stored and retrieved correctly.
     */
    public function testSimpleLocaleCodeIsHandledCorrectly(): void
    {
        // Act
        $this->locale->set('fr');
        $result = $this->locale->get();

        // Assert
        $this->assertSame('fr', $result, 'Simple locale code should be stored correctly');
    }
}
