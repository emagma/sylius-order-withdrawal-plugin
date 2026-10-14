<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection;

use LogicException;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class EmagmaSyliusOrderWithdrawalExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
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
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('emagma_sylius_order_withdrawal.notification_email', $config['notification_email']);
        $container->setParameter('emagma_sylius_order_withdrawal.prefer_channel_contact_email', $config['prefer_channel_contact_email']);
        $container->setParameter('emagma_sylius_order_withdrawal.send_acknowledgement', $config['send_acknowledgement']);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config'),
        );

        $loader->load('services.php');

        $limiterService = $config['rate_limiter_service'];

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

        $loader->load('services/rate_limiter.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrine($container);

        $this->prependDoctrineMigrations($container);

        $this->prependFooterHook($container);
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

    /**
     * Sylius 2.2 split the footer "help links" column into its own hookable menu.
     */
    private function prependFooterHook(ContainerBuilder $container): void
    {
        if ($this->supportsFooterHelpLinksHook()) {
            $container->prependExtensionConfig('sylius_twig_hooks', [
                'hooks' => [
                    'sylius_shop.base.footer.content.menu.help_links' => [
                        'emagma_withdrawal_request' => [
                            'template' => '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request_link.html.twig',
                            'priority' => -100,
                        ],
                    ],
                ],
            ]);

            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_shop.base.footer.content' => [
                    'emagma_withdrawal_request' => [
                        'template' => '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request.html.twig',
                        'priority' => 150,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Not InstalledVersions: Composer reports a dev checkout as "dev-main", which
     * version_compare ranks below every release, so Sylius 2.2 would take the pre-2.2 branch.
     */
    private function supportsFooterHelpLinksHook(): bool
    {
        return version_compare(SyliusCoreBundle::VERSION, '2.2.0', '>=');
    }
}
