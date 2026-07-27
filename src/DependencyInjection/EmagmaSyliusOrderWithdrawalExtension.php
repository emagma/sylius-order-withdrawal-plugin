<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class EmagmaSyliusOrderWithdrawalExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('emagma_sylius_order_withdrawal.notification_email', $config['notification_email']);
        $container->setParameter('emagma_sylius_order_withdrawal.prefer_channel_contact_email', $config['prefer_channel_contact_email']);

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config'),
        );

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
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

        $this->prependDoctrineMigrations($container);
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
}
