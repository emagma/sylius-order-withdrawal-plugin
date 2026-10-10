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
                    ->defaultNull()
                    ->info('Address that receives withdrawal-request notifications (default email channel). Optional — leave it unset when prefer_channel_contact_email is on and every channel carries a contact email. Accepts an environment variable, whose format is not validated; write it as %env(default::SAV_EMAIL)% so an unset variable resolves to no address instead of throwing.')
                    ->validate()
                        ->ifTrue(static fn (string|int|float|bool|null $value): bool => null !== $value && '' !== $value && false === filter_var((string) $value, \FILTER_VALIDATE_EMAIL))
                        ->thenInvalid('The notification_email "%s" is not a valid email address.')
                    ->end()
                ->end()
                ->booleanNode('prefer_channel_contact_email')
                    ->defaultTrue()
                    ->info('When true, notifications go to the originating channel contact email and fall back to notification_email. Set to false to always use notification_email.')
                ->end()
                ->booleanNode('send_acknowledgement')
                    ->defaultTrue()
                    ->info('Send an acknowledgement email to the address supplied on the public form. This address is not verified — disable it if you do not want your shop to send mail to unverified recipients.')
                ->end()
                ->scalarNode('rate_limiter_service')
                    ->defaultNull()
                    ->cannotBeEmpty()
                    ->info('Service id of a RateLimiterFactory throttling submissions to the public form — typically a "limiter.<name>" service declared under framework.rate_limiter, where you choose the policy, the cache pool (Redis) and the lock factory. Left null, submissions are not throttled at all. The plugin defines no limiter of its own.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
