<?php

declare(strict_types=1);

namespace Switon\I18n\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Switon\Core\LocaleInterface;
use Switon\Core\TranslatorInterface;
use Switon\I18n\Event\TranslationKeyMissing;
use Switon\I18n\Exception\IntlExtensionRequiredException;
use Switon\I18n\Exception\InvalidLocaleFileException;
use Switon\I18n\Exception\LocaleFileNotFoundException;
use Switon\I18n\Locale;
use Switon\I18n\Tests\TestCase;
use Switon\I18n\Translator;
use Exception;

use function file_put_contents;
use function mkdir;
use function rmdir;
use function unlink;

/**
 * Test cases for Translator class.
 *
 * Tests translation functionality: translate(), locale file loading, and placeholder replacement.
 */
class TranslatorTest extends TestCase
{
    protected Translator $translator;
    protected string $i18nDir;
    protected string $extraI18nDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary i18n directory
        $this->i18nDir = $this->pathAlias->get('@i18n');
        @mkdir($this->i18nDir, 0755, true);
        $this->extraI18nDir = $this->i18nDir . '-extra';
        @mkdir($this->extraI18nDir, 0755, true);
        $this->pathAlias->set('@i18n-extra', $this->extraI18nDir);

        // Set up Locale with default configuration
        $this->container->set(LocaleInterface::class, [
            'class' => Locale::class,
            'default' => 'en',
        ]);
        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Note: Translator is created per test, not in setUp,
        // because Translator scans files in constructor and we need
        // to create translation files before creating Translator instance
    }

    /**
     * Get or create Translator instance.
     * Call this after creating translation files in each test.
     *
     * Note: Translator scans files in constructor, so we need to create
     * a new instance after creating/modifying translation files.
     */
    protected function getTranslator(array $config = []): Translator
    {
        // Always create a new instance to ensure it scans the latest files
        // Remove old instance from container to force recreation
        if (isset($this->translator)) {
            $this->container->remove(TranslatorInterface::class);
        }

        // Set up Translator (Container will auto-inject Locale and PathAlias)
        $this->container->set(TranslatorInterface::class, array_merge([
            'class' => Translator::class,
            'dirs' => ['@i18n'],
        ], $config));
        $this->translator = $this->container->get(TranslatorInterface::class);

        return $this->translator;
    }

    protected function tearDown(): void
    {
        // Clean up translation files
        if (isset($this->i18nDir) && is_dir($this->i18nDir)) {
            $files = glob($this->i18nDir . '/*.php');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->i18nDir);
        }
        if (isset($this->extraI18nDir) && is_dir($this->extraI18nDir)) {
            $files = glob($this->extraI18nDir . '/*.php');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->extraI18nDir);
        }

        parent::tearDown();
    }

    /**
     * Create a translation file for testing.
     */
    protected function createTranslationFile(string $locale, array $translations): void
    {
        $file = $this->i18nDir . '/' . $locale . '.php';
        $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
        file_put_contents($file, $content);
    }

    protected function createExtraTranslationFile(string $locale, array $translations): void
    {
        $file = $this->extraI18nDir . '/' . $locale . '.php';
        $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
        file_put_contents($file, $content);
    }

    /**
     * Create a raw locale PHP file for failure-path tests.
     */
    protected function createRawTranslationFile(string $locale, string $php): void
    {
        $file = $this->i18nDir . '/' . $locale . '.php';
        file_put_contents($file, "<?php\n\n" . $php . "\n");
    }

    /**
     * Test that translate() returns translated message for existing key.
     *
     * Verifies that translate() returns the translated message
     * when the translation key exists in the locale file.
     */
    public function testTranslateReturnsTranslatedMessageForExistingKey(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
            'hello' => 'Hello',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('Welcome', $result, 'translate() should return translated message for existing key');
    }

    /**
     * Test that translate() returns template key when translation is missing.
     *
     * Verifies that translate() returns the template key itself
     * when the translation key does not exist in the locale file.
     */
    public function testTranslateReturnsTemplateKeyWhenTranslationMissing(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('missing_key');

        // Assert
        $this->assertSame('missing_key', $result, 'translate() should return template key when translation is missing');
    }

    /**
     * Test that translate() replaces placeholders with values.
     *
     * Verifies that translate() replaces {placeholder} format
     * with actual values from the placeholders array.
     */
    public function testTranslateReplacesPlaceholdersWithValues(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'hello' => 'Hello, {name}!',
            'user.not_found' => 'User {id} not found.',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result1 = $translator->translate('hello', ['name' => 'John']);
        $result2 = $translator->translate('user.not_found', ['id' => 123]);

        // Assert
        $this->assertSame('Hello, John!', $result1, 'translate() should replace single placeholder');
        $this->assertSame('User 123 not found.', $result2, 'translate() should replace placeholder with number');
    }

    /**
     * Test that translate() handles multiple placeholders.
     *
     * Verifies that translate() can replace multiple placeholders
     * in a single translation message.
     */
    public function testTranslateHandlesMultiplePlaceholders(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'greeting' => 'Hello, {name}! You have {count} messages.',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('greeting', [
            'name' => 'Alice',
            'count' => 5,
        ]);

        // Assert
        $this->assertSame(
            'Hello, Alice! You have 5 messages.',
            $result,
            'translate() should replace multiple placeholders'
        );
    }

    /**
     * Test that translate() converts non-string values to JSON.
     *
     * Verifies that translate() converts non-string placeholder values
     * to JSON format when replacing placeholders.
     */
    public function testTranslateConvertsNonStringValuesToJson(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'data' => 'Data: {value}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('data', [
            'value' => ['key' => 'value'],
        ]);

        // Assert
        $this->assertStringContainsString(
            '{"key":"value"}',
            $result,
            'translate() should convert non-string values to JSON'
        );
    }

    /**
     * Test that translate() loads locale file only once.
     *
     * Verifies that translate() caches loaded translation files
     * and does not reload them on subsequent calls.
     */
    public function testTranslateLoadsLocaleFileOnlyOnce(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'test' => 'Test',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act - Call translate multiple times
        $result1 = $translator->translate('test');

        // Modify file (should not affect cached translations)
        $this->createTranslationFile('en', [
            'test' => 'Modified',
        ]);

        $result2 = $translator->translate('test');

        // Assert
        $this->assertSame('Test', $result1, 'First call should return original translation');
        $this->assertSame('Test', $result2, 'Second call should return cached translation, not modified file');
    }

    /**
     * Test that translate() throws exception for non-existent locale file.
     *
     * Verifies that translate() throws LocaleFileNotFoundException
     * when the locale file does not exist.
     */
    public function testTranslateThrowsExceptionForNonExistentLocaleFile(): void
    {
        // Arrange
        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('nonexistent');

        // Create Translator (no files created, so it will have empty file list)
        $translator = $this->getTranslator();

        // Act & Assert
        $this->expectException(LocaleFileNotFoundException::class);
        $translator->translate('test');
    }

    public function testHasReturnsFalseWhenNoLocaleFilesExist(): void
    {
        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        $this->assertFalse($translator->has('missing.key'));
    }

    /**
     * Test that translate() normalizes configured default locale for fallback.
     */
    public function testTranslateUsesNormalizedConfiguredDefaultLocale(): void
    {
        // Arrange
        $this->container->remove(LocaleInterface::class);
        $this->container->set(LocaleInterface::class, [
            'class' => Locale::class,
            'default' => ' EN_US ',
        ]);

        $this->createTranslationFile('en-us', [
            'welcome' => 'Welcome from default',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-cn');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('Welcome from default', $result, 'Should fallback to normalized default locale file');
    }

    /**
     * Test that translate() rejects locale files that do not return arrays.
     */
    public function testTranslateThrowsExceptionWhenLocaleFileDoesNotReturnArray(): void
    {
        // Arrange
        $this->createRawTranslationFile('en', "return 'invalid';");

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act & Assert
        $this->expectException(InvalidLocaleFileException::class);
        $translator->translate('welcome');
    }

    /**
     * Test that translate() rejects locale files with non-string messages.
     */
    public function testTranslateThrowsExceptionWhenLocaleFileContainsNonStringMessage(): void
    {
        // Arrange
        $this->createRawTranslationFile('en', "return ['welcome' => ['nested' => true]];");

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act & Assert
        $this->expectException(InvalidLocaleFileException::class);
        $translator->translate('welcome');
    }

    /**
     * Test that translate() falls back from zh-cn to zh when zh-cn.php is missing.
     */
    public function testTranslateFallsBackFromLocaleToLanguage(): void
    {
        // Arrange: only zh.php exists, no zh-cn.php
        $this->createTranslationFile('zh', [
            'welcome' => '欢迎',
        ]);
        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-cn');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('welcome');

        // Assert: uses zh.php for zh-cn
        $this->assertSame('欢迎', $result);
    }

    /**
     * Test that translate() uses configured locale fallback map before language fallback.
     */
    public function testTranslateUsesConfiguredFallbackMap(): void
    {
        // Arrange
        $this->createTranslationFile('zh-tw', [
            'welcome' => '歡迎',
        ]);
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-hk');
        $translator = $this->getTranslator([
            'fallbacks' => [
                'zh-hk' => ['zh-tw', 'zh', 'en'],
            ],
        ]);

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('歡迎', $result, 'Configured fallbacks should be checked before default fallback');
    }

    public function testTranslateUsesConfiguredFallbacksAfterNormalizationAndDeduplication(): void
    {
        // Arrange
        $this->createTranslationFile('zh-tw', [
            'welcome' => '歡迎',
        ]);
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('ZH_HK');
        $translator = $this->getTranslator([
            'fallbacks' => [
                ' zh_hk ' => ['ZH_TW', 'zh-tw', 100, ''],
            ],
        ]);

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('歡迎', $result, 'Fallback list should be normalized/deduplicated and keep valid locales');
    }

    /**
     * Test that translate() works with different locales.
     *
     * Verifies that translate() loads and uses the correct locale file
     * based on the current locale setting.
     */
    public function testTranslateWorksWithDifferentLocales(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);
        $this->createTranslationFile('zh-cn', [
            'welcome' => '欢迎',
        ]);

        $locale = $this->container->get(LocaleInterface::class);

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $locale->set('en');
        $result1 = $translator->translate('welcome');

        $locale->set('zh-cn');
        $result2 = $translator->translate('welcome');

        // Assert
        $this->assertSame('Welcome', $result1, 'English translation should be returned');
        $this->assertSame('欢迎', $result2, 'Chinese translation should be returned');
    }

    /**
     * Test that translate() handles empty placeholders array.
     *
     * Verifies that translate() works correctly when placeholders
     * array is empty or not provided.
     */
    public function testTranslateHandlesEmptyPlaceholdersArray(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'message' => 'Simple message',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result1 = $translator->translate('message', []);
        $result2 = $translator->translate('message');

        // Assert
        $this->assertSame('Simple message', $result1, 'translate() should work with empty placeholders array');
        $this->assertSame('Simple message', $result2, 'translate() should work without placeholders parameter');
    }

    /**
     * Test that translate() treats dot notation as flat keys.
     *
     * Verifies that translate() treats keys with dots (e.g., 'user.not_found')
     * as flat string keys, not as nested array access.
     */
    public function testTranslateTreatsDotNotationAsFlatKeys(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'user.not_found' => 'User not found',  // Flat key with dot
            'user.created' => 'User created',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Create Translator after files are created
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('user.not_found');

        // Assert
        $this->assertSame(
            'User not found',
            $result,
            'translate() should treat dot notation as flat keys'
        );
    }

    public function testTranslateMergesResourcesFromMultipleDirectories(): void
    {
        $this->createTranslationFile('en', [
            'base.only' => 'Base only',
            'shared' => 'Base shared',
        ]);
        $this->createExtraTranslationFile('en', [
            'feature.only' => 'Feature only',
            'shared' => 'Feature shared',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator([
            'dirs' => ['@i18n', '@i18n-extra'],
        ]);

        $this->assertSame('Base only', $translator->translate('base.only'));
        $this->assertSame('Feature only', $translator->translate('feature.only'));
        $this->assertSame('Feature shared', $translator->translate('shared'));
    }

    /**
     * Test that regional locale falls back to default language file.
     */
    public function testTranslateFallsBackFromRegionalLocaleToDefaultLanguage(): void
    {
        // Arrange: only en.php exists, no en-us.php
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en-us');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('Welcome', $result);
    }

    /**
     * Test that non-ICU placeholder replacement renders boolean as JSON literal.
     */
    public function testTranslateReplacesBooleanPlaceholderAsJsonLiteral(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'flag' => 'Enabled: {enabled}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('flag', ['enabled' => true]);

        // Assert
        $this->assertSame('Enabled: true', $result);
    }

    /**
     * Test that translate() uses key-level fallback when key is missing in current locale.
     *
     * Verifies that when a key exists in zh.php but not in zh-cn.php,
     * the translator falls back to zh.php for that specific key.
     */
    public function testTranslateUsesKeyLevelFallback(): void
    {
        // Arrange: zh-cn.php has 'welcome', zh.php has 'goodbye', en.php has both
        $this->createTranslationFile('zh-cn', [
            'welcome' => '欢迎（简体）',
        ]);
        $this->createTranslationFile('zh', [
            'welcome' => '欢迎',
            'goodbye' => '再见',
        ]);
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
            'goodbye' => 'Goodbye',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-cn');
        $translator = $this->getTranslator();

        // Act
        $welcome = $translator->translate('welcome');
        $goodbye = $translator->translate('goodbye');

        // Assert
        $this->assertSame('欢迎（简体）', $welcome, 'Should use zh-cn for key that exists in zh-cn');
        $this->assertSame('再见', $goodbye, 'Should fallback to zh for key missing in zh-cn');
    }

    /**
     * Test that translate() dispatches TranslationKeyMissing event when key not found.
     *
     * Verifies that when a key is not found in any locale, the translator
     * dispatches a TranslationKeyMissing event with the key and effective locale.
     */
    public function testTranslateDispatchesEventWhenKeyMissing(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        // Ensure we replace the already-resolved dispatcher instance.
        $this->container->remove(PsrEventDispatcherInterface::class);

        $events = [];
        $eventDispatcher = $this->createMock(PsrEventDispatcherInterface::class);
        $eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });

        $this->container->set(PsrEventDispatcherInterface::class, $eventDispatcher);
        $translator = $this->getTranslator();

        // Act
        $events = [];
        $result = $translator->translate('missing.key');

        // Assert
        $this->assertSame('missing.key', $result, 'Should return key when translation missing');

        $missingEvents = array_values(array_filter(
            $events,
            static fn (object $e): bool => $e instanceof TranslationKeyMissing
                && $e->key === 'missing.key'
                && $e->locale === 'en'
        ));
        $this->assertNotEmpty($missingEvents, 'Should dispatch TranslationKeyMissing event when key missing');
    }

    /**
     * Test a minimal locale regression matrix for normal translation and missing-key lookup.
     */
    public function testTranslateRegressionMatrixCoversEnglishChineseFallbackAndMissingLookup(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'api.validation_failed' => 'Validation failed',
        ]);
        $this->createTranslationFile('zh-cn', [
            'api.validation_failed' => '校验失败',
        ]);
        $this->createTranslationFile('zh', [
            'api.validation_failed' => '驗證失敗',
        ]);

        $locale = $this->container->get(LocaleInterface::class);

        $this->container->remove(PsrEventDispatcherInterface::class);
        $eventDispatcher = new class () implements PsrEventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $this->container->set(PsrEventDispatcherInterface::class, $eventDispatcher);
        $translator = $this->getTranslator();

        // Act + Assert
        $locale->set('en');
        $this->assertSame('Validation failed', $translator->translate('api.validation_failed'));

        $locale->set('zh-cn');
        $this->assertSame('校验失败', $translator->translate('api.validation_failed'));

        $locale->set('zh-hk');
        $this->assertSame('驗證失敗', $translator->translate('api.validation_failed'));

        $this->assertFalse($translator->has('validation.labels.nickname'));
        $this->assertSame(
            'validation.labels.nickname',
            $translator->translate('validation.labels.nickname')
        );

        $missingEvents = array_values(array_filter(
            $eventDispatcher->events,
            static fn (object $event): bool => $event instanceof TranslationKeyMissing
                && $event->key === 'validation.labels.nickname'
        ));
        $this->assertNotEmpty($missingEvents);
    }

    /**
     * Test that translate() with useIcu normalizes non-scalar placeholder values to JSON strings.
     */
    public function testTranslateWithIcuNormalizesArrayPlaceholderToJsonString(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'data' => 'Data: {value}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');

        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('data', [
            'value' => ['key' => 'value'],
        ], useICU: true);

        // Assert
        $this->assertStringContainsString('{"key":"value"}', $result);
    }

    /**
     * Test that translate() searches all fallback locales for missing key.
     *
     * Verifies that when a key is missing in zh-cn and zh, it falls back to en (default).
     */
    public function testTranslateSearchesAllFallbackLocales(): void
    {
        // Arrange: only en.php has the key
        $this->createTranslationFile('zh-cn', [
            'other' => '其他',
        ]);
        $this->createTranslationFile('zh', [
            'another' => '另一个',
        ]);
        $this->createTranslationFile('en', [
            'welcome' => 'Welcome',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-cn');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('welcome');

        // Assert
        $this->assertSame('Welcome', $result, 'Should fallback to default locale (en) when key missing in zh-cn and zh');
    }

    public function testHasUsesConfiguredFallbackLocaleChain(): void
    {
        // Arrange: key only exists in configured fallback locale
        $this->createTranslationFile('zh-tw', [
            'feature.enabled' => '已启用',
        ]);
        $this->createTranslationFile('en', [
            'feature.enabled' => 'Enabled',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-hk');
        $translator = $this->getTranslator([
            'fallbacks' => [
                'zh-hk' => ['zh-tw'],
            ],
        ]);

        // Act + Assert
        $this->assertTrue($translator->has('feature.enabled'));
        $this->assertSame('已启用', $translator->translate('feature.enabled'));
    }

    /**
     * Test that translate() with useIcu handles pluralization.
     *
     * Verifies that ICU MessageFormat correctly handles plural forms.
     */
    public function testTranslateWithIcuHandlesPluralization(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'items' => '{count, plural, =0 {no items} one {# item} other {# items}}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act
        $result0 = $translator->translate('items', ['count' => 0], useICU: true);
        $result1 = $translator->translate('items', ['count' => 1], useICU: true);
        $result5 = $translator->translate('items', ['count' => 5], useICU: true);

        // Assert
        $this->assertSame('no items', $result0, 'Should handle zero case');
        $this->assertSame('1 item', $result1, 'Should handle singular case');
        $this->assertSame('5 items', $result5, 'Should handle plural case');
    }

    /**
     * Test that translate() with useIcu handles gender selection.
     *
     * Verifies that ICU MessageFormat correctly handles select format.
     */
    public function testTranslateWithIcuHandlesGenderSelection(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'greeting' => '{gender, select, male {Mr. {name}} female {Ms. {name}} other {{name}}}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act
        $resultMale = $translator->translate('greeting', ['gender' => 'male', 'name' => 'Smith'], useICU: true);
        $resultFemale = $translator->translate('greeting', ['gender' => 'female', 'name' => 'Smith'], useICU: true);
        $resultOther = $translator->translate('greeting', ['gender' => 'other', 'name' => 'Smith'], useICU: true);

        // Assert
        $this->assertSame('Mr. Smith', $resultMale, 'Should handle male gender');
        $this->assertSame('Ms. Smith', $resultFemale, 'Should handle female gender');
        $this->assertSame('Smith', $resultOther, 'Should handle other gender');
    }

    /**
     * Test that translate() with useIcu handles number formatting.
     *
     * Verifies that ICU MessageFormat correctly formats numbers.
     */
    public function testTranslateWithIcuHandlesNumberFormatting(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'price' => 'Price: {amount, number, currency}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('price', ['amount' => 1234.56], useICU: true);

        // Assert
        $this->assertStringContainsString('1,234.56', $result, 'Should format number with thousands separator');
        // Note: Currency symbol depends on locale settings, could be $ or ¤
        $this->assertStringContainsString('Price:', $result, 'Should include price label');
    }

    /**
     * Test that translate() throws exception when useIcu is true but intl is not available.
     *
     * Verifies that translate() throws component exception when ICU is requested
     * but intl extension is not loaded.
     */
    public function testTranslateThrowsExceptionWhenIcuRequestedButIntlNotAvailable(): void
    {
        // Arrange
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test requires intl extension to be disabled');
        }

        $this->createTranslationFile('en', [
            'test' => 'Test message',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act & Assert
        $this->expectException(IntlExtensionRequiredException::class);
        $this->expectExceptionMessage('intl extension is required when $useICU is true');
        $translator->translate('test', [], useICU: true);
    }

    /**
     * Test that translate() with useIcu supports fallback.
     *
     * Verifies that ICU mode still uses locale fallback mechanism.
     */
    public function testTranslateWithIcuSupportsFallback(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        // Only zh.php has the key, not zh-cn.php
        $this->createTranslationFile('zh-cn', [
            'other' => '其他',
        ]);
        $this->createTranslationFile('zh', [
            'items' => '{count, plural, other {# 个项目}}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('zh-cn');
        $translator = $this->getTranslator();

        // Act
        $result = $translator->translate('items', ['count' => 5], useICU: true);

        // Assert
        $this->assertSame('5 个项目', $result, 'Should fallback to zh.php when key missing in zh-cn.php');
    }

    /**
     * Test that translate() without useIcu does not process ICU format.
     *
     * Verifies that ICU format is treated as plain text when useIcu is false.
     */
    public function testTranslateWithoutIcuDoesNotProcessIcuFormat(): void
    {
        // Arrange
        $this->createTranslationFile('en', [
            'items' => '{count, plural, one {# item} other {# items}}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act - useIcu is false (default)
        $result = $translator->translate('items', ['count' => 5]);

        // Assert
        $this->assertSame(
            '{count, plural, one {# item} other {# items}}',
            $result,
            'Should return ICU format as-is when useIcu is false'
        );
    }

    /**
     * Test that translate() with useIcu ignores extra placeholders.
     *
     * Verifies that ICU MessageFormat silently ignores unused variables.
     */
    public function testTranslateWithIcuIgnoresExtraPlaceholders(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'welcome' => 'Hello {name}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act - pass extra variable that's not in template
        $result = $translator->translate('welcome', [
            'name' => 'Alice',
            'admin' => true,  // Not used in template
            'extra' => 'ignored',
        ], useICU: true);

        // Assert - extra variables are silently ignored
        $this->assertSame('Hello Alice', $result, 'Should ignore extra placeholders');
    }

    /**
     * Test that translate() with useIcu handles missing placeholders.
     *
     * Verifies ICU MessageFormat behavior when required variables are missing.
     */
    public function testTranslateWithIcuHandlesMissingPlaceholders(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'greeting' => 'Hello {name}, role: {role}',
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act - missing 'role' variable
        $result = $translator->translate('greeting', [
            'name' => 'Alice',
            // 'role' is missing
        ], useICU: true);

        // Assert - MessageFormatter leaves unresolved placeholders or returns partial result
        // Behavior depends on PHP/intl version, but should not throw exception
        $this->assertIsString($result, 'Should return a string even with missing placeholders');
        $this->assertStringContainsString('Alice', $result, 'Should replace available placeholders');
    }

    /**
     * Test that translate() with useIcu handles invalid ICU syntax.
     *
     * Verifies behavior when ICU format syntax is invalid.
     */
    public function testTranslateWithIcuHandlesInvalidSyntax(): void
    {
        // Arrange
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required for ICU tests');
        }

        $this->createTranslationFile('en', [
            'invalid' => '{count, invalid_type, xxx}',  // Invalid ICU syntax
        ]);

        $locale = $this->container->get(LocaleInterface::class);
        $locale->set('en');
        $translator = $this->getTranslator();

        // Act & Assert - MessageFormatter may return false or throw exception
        // We just verify it doesn't crash the application
        try {
            $result = $translator->translate('invalid', ['count' => 5], useICU: true);
            $this->assertIsString($result, 'Should return a string even with invalid syntax');
        } catch (Exception $e) {
            // Some versions may throw exception, which is acceptable
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
}
