<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection;

use function filter_var;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('emagma_sylius_order_withdrawal');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('notification_email')
                    ->cannotBeEmpty()
                    ->defaultValue('notifications@example.com')
                    ->info('Address that receives withdrawal-request notifications (default email channel).')
                    ->validate()
                        ->ifTrue(static fn (string $value): bool => false === filter_var($value, \FILTER_VALIDATE_EMAIL))
                        ->thenInvalid('The notification_email "%s" is not a valid email address.')
                    ->end()
                ->end()
                ->booleanNode('prefer_channel_contact_email')
                    ->defaultTrue()
                    ->info('When true, notifications go to the originating channel contact email and fall back to notification_email. Set to false to always use notification_email.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
