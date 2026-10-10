<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Declaring the notifier in a *separate* definition is the point: it proves the tag reaches
 * host-declared implementations, not only those in the plugin's own file. Both passes are
 * required — 6.4's loader also registered the attribute while globbing, 7.4 does not.
 */
final class NotifierAutoconfigurationTest extends TestCase
{
    public function testAHostApplicationNotifierIsTaggedWithoutDeclaringTheTag(): void
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../config'));
        $loader->load('services.xml');

        $definition = new Definition(HostAppNotifier::class);
        $definition->setAutoconfigured(true);
        $container->setDefinition(HostAppNotifier::class, $definition);

        (new RegisterAutoconfigureAttributesPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);

        self::assertTrue(
            $container->getDefinition(HostAppNotifier::class)->hasTag('emagma_sylius_order_withdrawal.notifier'),
            'A host application notifier must receive the chain tag through autoconfiguration.',
        );
    }
}

final class HostAppNotifier implements WithdrawalRequestNotifierInterface
{
    public function notify(WithdrawalRequest $request): void
    {
    }
}
