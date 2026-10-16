<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataCollectionFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestCreateProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestTransitionProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection\EmagmaSyliusOrderWithdrawalExtension;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestRateLimitListener;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

final class EmagmaSyliusOrderWithdrawalExtensionTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const CONFIG = [['notification_email' => 'sav@example.com']];

    private const LIMITER_FACTORY_ID = 'emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request';

    /**
     * Symfony's PSR-4 loader registers an excluded class as a stub Definition tagged
     * "container.excluded", pruned only when the container compiles. load() alone never
     * compiles, so hasDefinition() would report the listener as present; running
     * RemoveUnusedDefinitionsPass reproduces what a real container ends up with.
     */
    public function testTheRateLimiterIsNotWiredByDefault(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(self::CONFIG, $container);

        self::assertTrue(
            $container->getDefinition(WithdrawalRequestRateLimitListener::class)->hasTag('container.excluded'),
            'Expected the listener to only exist as an excluded prototype stub when no rate_limiter_service is configured.',
        );

        (new RemoveUnusedDefinitionsPass())->process($container);

        self::assertFalse($container->hasDefinition(WithdrawalRequestRateLimitListener::class));
    }

    public function testItWiresTheListenerToTheConfiguredLimiterService(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['rate_limiter_service' => 'limiter.withdrawal']],
            $container,
        );

        self::assertTrue($container->hasDefinition(WithdrawalRequestRateLimitListener::class));
        self::assertFalse(
            $container->getDefinition(WithdrawalRequestRateLimitListener::class)->hasTag('container.excluded'),
            'The wired listener must be the real services/rate_limiter.php definition, not the leftover prototype stub.',
        );
        self::assertSame('limiter.withdrawal', (string) $container->getAlias(self::LIMITER_FACTORY_ID));
    }

    public function testItDefinesNoLimiterOfItsOwn(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['rate_limiter_service' => 'limiter.withdrawal']],
            $container,
        );

        self::assertFalse($container->hasDefinition(self::LIMITER_FACTORY_ID));
        self::assertFalse($container->hasDefinition('emagma_sylius_order_withdrawal.rate_limiter.storage'));
    }

    public function testItExposesTheProcessedConfigurationAsParameters(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['prefer_channel_contact_email' => false, 'send_acknowledgement' => false]],
            $container,
        );

        self::assertSame('sav@example.com', $container->getParameter('emagma_sylius_order_withdrawal.notification_email'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.prefer_channel_contact_email'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.send_acknowledgement'));
    }

    public function testTheNotificationEmailParameterIsNullWhenUnset(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load([[]], $container);

        self::assertNull($container->getParameter('emagma_sylius_order_withdrawal.notification_email'));
    }

    public function testItRegistersTheDoctrineXmlMapping(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        $mapping = $container->getExtensionConfig('doctrine')[0]['orm']['mappings']['EmagmaSyliusOrderWithdrawalPlugin'];

        self::assertSame('xml', $mapping['type']);
        self::assertSame('config/doctrine', $mapping['dir']);
        self::assertTrue($mapping['is_bundle']);
    }

    /**
     * Repeats the '2.2.0' and '>=' literals rather than importing the predicate, so changing
     * either in the extension fails here. One run covers one branch; ci.yml runs 2.0, 2.1 and
     * 2.2, so the matrix covers both.
     */
    public function testTheRealExtensionPicksTheHookMatchingTheInstalledSyliusVersion(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        $expectedKey = version_compare(SyliusCoreBundle::VERSION, '2.2.0', '>=')
            ? 'sylius_shop.base.footer.content.menu.help_links'
            : 'sylius_shop.base.footer.content';

        self::assertArrayHasKey($expectedKey, $this->twigHooks($container));
    }

    public function testAnEnvNotificationEmailDoesNotBreakTheBuildWhenApiPlatformIsRegistered(): void
    {
        $container = $this->compile(['notification_email' => '%env(default::WITHDRAWAL_NOTIFICATION_EMAIL)%']);

        self::assertSame([], $container->getExtensionConfig('api_platform'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.admin'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.shop'));
    }

    public function testItMapsThePluginGraphWhenTheAbstractionBundleIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class() extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'sylius_state_machine_abstraction';
            }
        });

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        self::assertContains(
            ['graphs_to_adapters_mapping' => ['emagma_sylius_order_withdrawal_request' => 'symfony_workflow']],
            $container->getExtensionConfig('sylius_state_machine_abstraction'),
        );
    }

    public function testItLeavesTheAbstractionConfigAloneWhenTheBundleIsAbsent(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('sylius_state_machine_abstraction'));
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidApiConfigurations')]
    public function testInvalidApiConfigurationsFailLoudly(array $config, bool $withApiPlatform, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->boot(['notification_email' => 'sav@example.com'] + $config, $withApiPlatform);
    }

    /** @return iterable<string, array{array<string, mixed>, bool, string}> */
    public static function invalidApiConfigurations(): iterable
    {
        yield 'admin without API Platform' => [['api' => ['admin' => true]], false, 'API Platform is not registered'];
        yield 'shop without API Platform' => [['api' => ['shop' => true], 'rate_limiter_service' => 'limiter.x'], false, 'API Platform is not registered'];
        yield 'shop without rate limiter' => [['api' => ['shop' => true]], true, 'requires rate_limiter_service'];
        yield 'env switch' => [['api' => ['admin' => '%env(bool:WITHDRAWAL_API)%']], true, 'compile-time boolean'];
        yield 'shop ~ without rate limiter' => [['api' => ['shop' => null]], true, 'requires rate_limiter_service'];
    }

    /** @param list<array<string, mixed>> $configs */
    #[DataProvider('tildeSwitches')]
    public function testATildeSwitchMeansTrueLikeTheProcessedConfiguration(array $configs, bool $admin, bool $shop): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['ApiPlatformBundle' => ApiPlatformBundle::class]);

        (new EmagmaSyliusOrderWithdrawalExtension())->load($configs, $container);

        self::assertSame($admin, $container->getParameter('emagma_sylius_order_withdrawal.api.admin'));
        self::assertSame($shop, $container->getParameter('emagma_sylius_order_withdrawal.api.shop'));
    }

    /** @return iterable<string, array{list<array<string, mixed>>, bool, bool}> */
    public static function tildeSwitches(): iterable
    {
        yield 'admin ~' => [[['api' => ['admin' => null]]], true, false];
        yield 'a later admin ~ overrides an earlier false' => [[['api' => ['admin' => false]], ['api' => ['admin' => null]]], true, false];
        yield 'shop ~ with a rate limiter' => [[['api' => ['shop' => null], 'rate_limiter_service' => 'limiter.x']], false, true];
    }

    public function testAnEnvSwitchIsRejectedAfterPlaceholderResolution(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('compile-time boolean');

        $this->compile(['notification_email' => 'sav@example.com', 'api' => ['admin' => '%env(bool:WITHDRAWAL_API)%']]);
    }

    public function testAParameterSwitchIsResolved(): void
    {
        $container = $this->compile(
            ['notification_email' => 'sav@example.com', 'api' => ['admin' => '%app.withdrawal_api%']],
            ['app.withdrawal_api' => true],
        );

        self::assertTrue($container->getParameter('emagma_sylius_order_withdrawal.api.admin'));
    }

    public function testTheDefaultConfigurationAddsNoApi(): void
    {
        $container = $this->boot(['notification_email' => 'sav@example.com']);

        self::assertFalse($container->hasDefinition(WithdrawalRequestCreateProcessor::class));
        self::assertFalse($container->hasDefinition(WithdrawalRequestTransitionProcessor::class));
        self::assertSame([], $container->getExtensionConfig('api_platform'));
    }

    public function testTheAdminSwitchRegistersTheAdminOperationsOnly(): void
    {
        $container = $this->containerWithApi(['admin' => true]);

        self::assertTrue($container->hasDefinition(WithdrawalRequestCreateProcessor::class));
        self::assertTrue($container->hasDefinition(WithdrawalRequestTransitionProcessor::class));
        self::assertSame([$this->configDir()], $this->apiPlatformPaths($container));
    }

    public function testTheShopSwitchAloneRegistersTheShopOperationOnly(): void
    {
        $container = $this->containerWithApi(['shop' => true]);

        self::assertTrue($container->hasDefinition(WithdrawalRequestCreateProcessor::class));
        self::assertSame([$this->configDir()], $this->apiPlatformPaths($container));
    }

    public function testTheOperationsFilterDecoratesTheResourceMetadataFactoryBelowItsCache(): void
    {
        $container = $this->containerWithApi(['admin' => true]);

        $definition = $container->getDefinition(WithdrawalRequestOperationsResourceMetadataCollectionFactory::class);
        self::assertSame(['api_platform.metadata.resource.metadata_collection_factory', null, 750], $definition->getDecoratedService());
        self::assertSame(
            ['%emagma_sylius_order_withdrawal.model.withdrawal_request.class%', '%emagma_sylius_order_withdrawal.api.admin%', '%emagma_sylius_order_withdrawal.api.shop%'],
            array_map(strval(...), \array_slice($definition->getArguments(), 1)),
        );
        self::assertFalse(
            $this->boot(['notification_email' => 'sav@example.com'])->hasDefinition(WithdrawalRequestOperationsResourceMetadataCollectionFactory::class),
        );
    }

    public function testBothSwitchesRegisterTheSameSingleResourceDirectory(): void
    {
        $container = $this->containerWithApi(['admin' => true, 'shop' => true]);

        self::assertSame([$this->configDir()], $this->apiPlatformPaths($container));
    }

    /** @param array<string, mixed> $config */
    private function boot(array $config, bool $withApiPlatform = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $withApiPlatform ? ['ApiPlatformBundle' => ApiPlatformBundle::class] : []);
        $container->prependExtensionConfig('emagma_sylius_order_withdrawal', $config);

        $extension = new EmagmaSyliusOrderWithdrawalExtension();
        $extension->prepend($container);
        $extension->load($container->getExtensionConfig('emagma_sylius_order_withdrawal'), $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $parameters
     */
    private function compile(array $config, array $parameters = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['ApiPlatformBundle' => ApiPlatformBundle::class]);
        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }
        $container->registerExtension(new EmagmaSyliusOrderWithdrawalExtension());
        $container->loadFromExtension('emagma_sylius_order_withdrawal', $config);

        (new MergeExtensionConfigurationPass(['emagma_sylius_order_withdrawal']))->process($container);

        return $container;
    }

    /** @param array<string, bool> $api */
    private function containerWithApi(array $api): ContainerBuilder
    {
        return $this->boot([
            'notification_email' => 'sav@example.com',
            'rate_limiter_service' => 'limiter.withdrawal_request',
            'api' => $api,
        ]);
    }

    public function testTheShopApiIsThrottledByASecondListenerWhenALimiterIsConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['ApiPlatformBundle' => ApiPlatformBundle::class]);

        (new EmagmaSyliusOrderWithdrawalExtension())->load([[
            'notification_email' => 'sav@example.com',
            'rate_limiter_service' => 'limiter.withdrawal_request',
            'api' => ['shop' => true],
        ]], $container);

        $definition = $container->getDefinition('emagma_sylius_order_withdrawal.rate_limit_listener.shop_api');
        self::assertSame(WithdrawalRequestRateLimitListener::class, $definition->getClass());
        self::assertSame('emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post', $definition->getArgument(1));
        self::assertTrue($container->hasDefinition(WithdrawalRequestRateLimitListener::class), 'The form listener must stay registered as before.');
    }

    public function testNoSecondListenerWithoutTheShopApi(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['ApiPlatformBundle' => ApiPlatformBundle::class]);

        (new EmagmaSyliusOrderWithdrawalExtension())->load([[
            'notification_email' => 'sav@example.com',
            'rate_limiter_service' => 'limiter.withdrawal_request',
            'api' => ['admin' => true],
        ]], $container);

        self::assertFalse($container->hasDefinition('emagma_sylius_order_withdrawal.rate_limit_listener.shop_api'));
    }

    /** @return list<string> */
    private function apiPlatformPaths(ContainerBuilder $container): array
    {
        $paths = [];
        foreach ($container->getExtensionConfig('api_platform') as $config) {
            foreach ($config['mapping']['paths'] ?? [] as $path) {
                $paths[] = (string) realpath($path);
            }
        }
        sort($paths);

        return $paths;
    }

    private function configDir(): string
    {
        return (string) realpath(__DIR__ . '/../../../config/api_platform');
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function twigHooks(ContainerBuilder $container): array
    {
        return $container->getExtensionConfig('sylius_twig_hooks')[0]['hooks'];
    }
}
