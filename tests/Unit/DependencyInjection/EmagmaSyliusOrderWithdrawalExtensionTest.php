<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle;
use Emagma\SyliusOrderWithdrawalPlugin\Api\DataPersister\WithdrawalRequestDataPersister;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Serializer\AvailableTransitionsNormalizer;
use Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection\EmagmaSyliusOrderWithdrawalExtension;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestRateLimitListener;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\DependencyInjection\Configuration as StateMachineAbstractionConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;
use Symfony\Component\Yaml\Yaml;

final class EmagmaSyliusOrderWithdrawalExtensionTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const CONFIG = [['notification_email' => 'sav@example.com']];

    private const LIMITER_FACTORY_ID = 'emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request';

    private const SHOP_API_LISTENER_ID = 'emagma_sylius_order_withdrawal.rate_limit_listener.shop_api';

    private const API_SERVICE_IDS = [
        WithdrawalRequestOperationsResourceMetadataFactory::class,
        AvailableTransitionsNormalizer::class,
        WithdrawalRequestDataPersister::class,
        'emagma_sylius_order_withdrawal.api.controller.apply_transition',
        'emagma_sylius_order_withdrawal.api.search_filter.admin.withdrawal_request',
        'emagma_sylius_order_withdrawal.api.date_filter.admin.withdrawal_request',
        'emagma_sylius_order_withdrawal.api.order_filter.admin.withdrawal_request',
    ];

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
            'The wired listener must be the real service.xml definition, not the leftover prototype stub.',
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
     * On this line the shop footer is injected through a sylius_ui template event rather than a
     * prepended Twig hook, so the wiring lives in config and is asserted there — this fails if
     * the event key or the template path drifts apart.
     */
    public function testItInjectsTheShopFooterThroughASyliusUiEvent(): void
    {
        /** @var array{sylius_ui: array{events: array<string, array{blocks: array<string, array{template: string}>}>}} $config */
        $config = Yaml::parseFile(__DIR__ . '/../../../config/app/sylius_ui.yaml');

        $blocks = $config['sylius_ui']['events']['sylius.shop.layout.footer.grid']['blocks'];

        self::assertSame(
            '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request.html.twig',
            $blocks['emagma_withdrawal_request']['template'],
        );
    }

    public function testTheDefaultConfigurationAddsNoApi(): void
    {
        $container = $this->boot(['notification_email' => 'sav@example.com', 'rate_limiter_service' => 'limiter.withdrawal_request']);

        foreach (self::API_SERVICE_IDS as $id) {
            self::assertFalse($this->isRegistered($container, $id), \sprintf('"%s" must not be registered when the API is off.', $id));
        }
        self::assertFalse($container->hasDefinition(self::SHOP_API_LISTENER_ID));
        self::assertSame([], $container->getExtensionConfig('api_platform'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.admin'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.shop'));
    }

    /** @param array<string, bool> $api */
    #[DataProvider('enabledSwitches')]
    public function testEitherSwitchRegistersTheApiServicesAndTheResourceDirectory(array $api): void
    {
        $container = $this->containerWithApi($api);

        foreach (self::API_SERVICE_IDS as $id) {
            self::assertTrue($this->isRegistered($container, $id), \sprintf('"%s" must be registered when the API is on.', $id));
        }
        self::assertSame([$this->resourceDir()], $this->apiPlatformPaths($container));
    }

    /** @return iterable<string, array{array<string, bool>}> */
    public static function enabledSwitches(): iterable
    {
        yield 'admin only' => [['admin' => true]];
        yield 'shop only' => [['shop' => true]];
        yield 'both' => [['admin' => true, 'shop' => true]];
    }

    public function testTheOperationsFilterDecoratesTheLegacyResourceMetadataFactoryBelowItsCache(): void
    {
        $definition = $this->containerWithApi(['admin' => true])->getDefinition(WithdrawalRequestOperationsResourceMetadataFactory::class);

        self::assertSame(['api_platform.metadata.resource.metadata_factory', null, 1], $definition->getDecoratedService());
        self::assertSame(
            ['%emagma_sylius_order_withdrawal.model.withdrawal_request.class%', '%emagma_sylius_order_withdrawal.api.admin%', '%emagma_sylius_order_withdrawal.api.shop%'],
            array_map(strval(...), \array_slice($definition->getArguments(), 1)),
        );
    }

    public function testTheApiServicesAreTaggedAsInTheSyliusCore(): void
    {
        $container = $this->containerWithApi(['admin' => true, 'shop' => true]);

        self::assertSame([['priority' => 64]], $container->getDefinition(AvailableTransitionsNormalizer::class)->getTag('serializer.normalizer'));
        self::assertSame([['priority' => 10]], $container->getDefinition(WithdrawalRequestDataPersister::class)->getTag('api_platform.data_persister'));
        self::assertSame('api_platform.doctrine.orm.data_persister', (string) $container->getDefinition(WithdrawalRequestDataPersister::class)->getArgument(0));
        self::assertTrue($container->getDefinition('emagma_sylius_order_withdrawal.api.controller.apply_transition')->isPublic());
        self::assertSame(
            ['submittedAt' => null, 'id' => null],
            $container->getDefinition('emagma_sylius_order_withdrawal.api.order_filter.admin.withdrawal_request')->getArgument(0),
        );
    }

    public function testAnEnvNotificationEmailDoesNotBreakTheBuildWhenApiPlatformIsRegistered(): void
    {
        $container = $this->compile(['notification_email' => '%env(default::WITHDRAWAL_NOTIFICATION_EMAIL)%']);

        self::assertSame([], $container->getExtensionConfig('api_platform'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.admin'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.api.shop'));
    }

    public function testItMapsThePluginGraphToWinzouWhenTheAbstractionBundleIsRegistered(): void
    {
        $container = $this->containerWithAbstractionExtension();

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        self::assertContains(
            ['graphs_to_adapters_mapping' => ['emagma_sylius_order_withdrawal_request' => 'winzou_state_machine']],
            $container->getExtensionConfig('sylius_state_machine_abstraction'),
        );
    }

    public function testAnAppMappingOfThePluginGraphWins(): void
    {
        $container = $this->containerWithAbstractionExtension();
        $container->loadFromExtension('sylius_state_machine_abstraction', [
            'graphs_to_adapters_mapping' => ['emagma_sylius_order_withdrawal_request' => 'symfony_workflow'],
        ]);

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        $config = (new Processor())->processConfiguration(
            new StateMachineAbstractionConfiguration(),
            $container->getExtensionConfig('sylius_state_machine_abstraction'),
        );

        self::assertSame('symfony_workflow', $config['graphs_to_adapters_mapping']['emagma_sylius_order_withdrawal_request']);
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
        self::assertSame([$this->resourceDir()], $this->apiPlatformPaths($container));
    }

    public function testTheShopApiIsThrottledByASecondListenerOnTheGeneratedRoute(): void
    {
        $container = $this->containerWithApi(['shop' => true]);

        $definition = $container->getDefinition(self::SHOP_API_LISTENER_ID);
        self::assertSame(WithdrawalRequestRateLimitListener::class, $definition->getClass());
        self::assertSame(self::LIMITER_FACTORY_ID, (string) $definition->getArgument(0));
        self::assertSame('api_withdrawal_requests_shop_post_collection', $definition->getArgument(1));
        self::assertSame([['event' => 'kernel.request', 'priority' => 8]], $definition->getTag('kernel.event_listener'));
        self::assertTrue($this->isRegistered($container, WithdrawalRequestRateLimitListener::class), 'The form listener must stay registered as before.');
    }

    public function testNoSecondListenerWithoutTheShopApi(): void
    {
        $container = $this->containerWithApi(['admin' => true]);

        self::assertFalse($container->hasDefinition(self::SHOP_API_LISTENER_ID));
        self::assertTrue($this->isRegistered($container, WithdrawalRequestRateLimitListener::class));
    }

    public function testTheAdminApiStillLoadsWithoutARateLimiter(): void
    {
        $container = $this->boot(['notification_email' => 'sav@example.com', 'api' => ['admin' => true]]);

        self::assertTrue($this->isRegistered($container, WithdrawalRequestOperationsResourceMetadataFactory::class));
        self::assertFalse($this->isRegistered($container, WithdrawalRequestRateLimitListener::class));
        self::assertFalse($container->hasAlias(self::LIMITER_FACTORY_ID));
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

    private function containerWithAbstractionExtension(): ContainerBuilder
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

        return $container;
    }

    /** Excluded prototype classes stay as "container.excluded" stubs until the container compiles. */
    private function isRegistered(ContainerBuilder $container, string $id): bool
    {
        return $container->hasDefinition($id) && !$container->getDefinition($id)->hasTag('container.excluded');
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

    private function resourceDir(): string
    {
        return (string) realpath(__DIR__ . '/../../../config/api_resources');
    }
}
