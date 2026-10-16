<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection;

use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestTransition;
use LogicException;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class EmagmaSyliusOrderWithdrawalExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $api = $this->apiSwitches($container, $configs);

        $configuration = new Configuration();

        /**
         * processConfiguration() is only typed as an array of mixed, so the shape Configuration
         * already guarantees is restated here — that is what makes the parameters and the alias
         * below type-safe rather than mixed.
         *
         * @var array{
         *     notification_email: string|null,
         *     prefer_channel_contact_email: bool,
         *     send_acknowledgement: bool,
         *     rate_limiter_service: string|null,
         *     api: array{admin: bool, shop: bool},
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('emagma_sylius_order_withdrawal.notification_email', $config['notification_email']);
        $container->setParameter('emagma_sylius_order_withdrawal.prefer_channel_contact_email', $config['prefer_channel_contact_email']);
        $container->setParameter('emagma_sylius_order_withdrawal.send_acknowledgement', $config['send_acknowledgement']);
        $container->setParameter('emagma_sylius_order_withdrawal.api.admin', $api['admin']);
        $container->setParameter('emagma_sylius_order_withdrawal.api.shop', $api['shop']);

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config'),
        );

        $loader->load('services.xml');

        if ($api['admin'] || $api['shop']) {
            $loader->load('services/api.xml');
        }

        $this->loadRateLimiter($config['rate_limiter_service'], $api['shop'], $container, $loader);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrine($container);

        $this->prependDoctrineMigrations($container);

        $this->prependStateMachineAdapter($container);

        $this->prependApiPlatform($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Emagma\SyliusOrderWithdrawalPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@EmagmaSyliusOrderWithdrawalPlugin/src/Migrations';
    }

    /**
     * @return string[]
     */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return ['Sylius\Bundle\CoreBundle\Migrations'];
    }

    private function loadRateLimiter(?string $limiterService, bool $shopApi, ContainerBuilder $container, XmlFileLoader $loader): void
    {
        if (null === $limiterService) {
            return;
        }

        if (!class_exists(RateLimiterFactory::class)) {
            throw new LogicException(
                'emagma_sylius_order_withdrawal.rate_limiter_service is set but symfony/rate-limiter is not installed. Run "composer require symfony/rate-limiter", or unset it to leave the public form unthrottled.',
            );
        }

        $container->setAlias(
            'emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request',
            $limiterService,
        );

        $loader->load('services/rate_limiter.xml');

        if ($shopApi) {
            $loader->load('services/rate_limiter_shop_api.xml');
        }
    }

    private function prependDoctrine(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'EmagmaSyliusOrderWithdrawalPlugin' => [
                        'is_bundle' => true,
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Emagma\SyliusOrderWithdrawalPlugin\Entity',
                        'alias' => 'EmagmaSyliusOrderWithdrawalPlugin',
                    ],
                ],
            ],
        ]);
    }

    private function prependStateMachineAdapter(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_state_machine_abstraction')) {
            return;
        }

        $container->prependExtensionConfig('sylius_state_machine_abstraction', [
            'graphs_to_adapters_mapping' => [WithdrawalRequestTransition::GRAPH => 'winzou_state_machine'],
        ]);
    }

    private function prependApiPlatform(ContainerBuilder $container): void
    {
        $api = $this->apiSwitches($container, $container->getExtensionConfig($this->getAlias()));

        if (!$api['admin'] && !$api['shop']) {
            return;
        }

        $container->prependExtensionConfig('api_platform', ['mapping' => ['paths' => [
            \dirname(__DIR__, 2) . '/config/api_resources',
        ]]]);
    }

    /**
     * @param array<array-key, mixed> $configs
     *
     * @return array{admin: bool, shop: bool}
     */
    private function apiSwitches(ContainerBuilder $container, array $configs): array
    {
        $api = ['admin' => false, 'shop' => false];
        $rateLimiterService = null;

        foreach ($configs as $config) {
            if (!\is_array($config)) {
                continue;
            }
            $apiConfig = $config['api'] ?? null;
            foreach (['admin', 'shop'] as $switch) {
                if (\is_array($apiConfig) && \array_key_exists($switch, $apiConfig)) {
                    $api[$switch] = $this->compileTimeBoolean($container, $switch, $apiConfig[$switch] ?? true);
                }
            }
            if (\array_key_exists('rate_limiter_service', $config)) {
                $rateLimiterService = $config['rate_limiter_service'];
            }
        }

        if (($api['admin'] || $api['shop']) && !$this->isApiPlatformRegistered($container)) {
            throw new LogicException('emagma_sylius_order_withdrawal.api.admin or api.shop is true but API Platform is not registered. Register ApiPlatformBundle (Sylius ships it), or turn both switches off.');
        }

        if ($api['shop'] && null === $rateLimiterService) {
            throw new LogicException('emagma_sylius_order_withdrawal.api.shop requires rate_limiter_service: the shop endpoint is public and sends an email to the submitted address on every call. Declare a limiter under framework.rate_limiter and name it in rate_limiter_service.');
        }

        return $api;
    }

    private function compileTimeBoolean(ContainerBuilder $container, string $switch, mixed $value): bool
    {
        if (\is_string($value) && !str_contains($value, '%env(')) {
            $value = $container->getParameterBag()->resolveValue($value);
        }

        if (!\is_bool($value)) {
            throw new LogicException(sprintf('emagma_sylius_order_withdrawal.api.%s must be a compile-time boolean (true, false or a %%parameter%% holding one); environment variables are not supported because the switch decides routes and API metadata.', $switch));
        }

        return $value;
    }

    private function isApiPlatformRegistered(ContainerBuilder $container): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return isset($bundles['ApiPlatformBundle']);
    }
}
