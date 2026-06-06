<?php

declare(strict_types=1);

namespace Switon\I18n\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\ContextManagerInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Testing\Container;

use function sys_get_temp_dir;

/**
 * Base test case for I18n tests.
 *
 * Provides common functionality for all I18n tests, including Container and ContextManager initialization.
 */
abstract class TestCase extends BaseTestCase
{
    protected Container $container;
    protected ContextManagerInterface $contextManager;
    protected PathAlias $pathAlias;
    protected string|false|null $providerAutoRegisterEnv = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerAutoRegisterEnv = getenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=1');

        // Use pre-configured test container (ContextManagerInterface and PathAliasInterface are already registered)
        $this->container = new Container();

        // Get ContextManager from container
        $this->contextManager = $this->container->get(ContextManagerInterface::class);

        // Get PathAlias from container and set @i18n alias for tests
        $this->pathAlias = $this->container->get(PathAliasInterface::class);
        $this->pathAlias->set('@i18n', sys_get_temp_dir() . '/switon_i18n_test_' . uniqid('', true));
    }

    protected function tearDown(): void
    {
        if ($this->providerAutoRegisterEnv === false || $this->providerAutoRegisterEnv === null) {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        } else {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=' . $this->providerAutoRegisterEnv);
        }

        parent::tearDown();
    }
}
