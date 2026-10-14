<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestListener;
use Emagma\SyliusOrderWithdrawalPlugin\Form\Type\WithdrawalRequestType;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailer;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\ChainWithdrawalRequestNotifier;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\DefaultRecipientResolver;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\RecipientResolverInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolver;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolverInterface;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('emagma_sylius_order_withdrawal.form.type.withdrawal_request.validation_groups', ['sylius'])
    ;

    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
    ;

    $services->load('Emagma\\SyliusOrderWithdrawalPlugin\\', '../src/*')
        ->exclude('../src/{DependencyInjection,Entity,Migrations,Model,Event,EmagmaSyliusOrderWithdrawalPlugin.php,EventListener/WithdrawalRequestRateLimitListener.php}')
    ;

    $services->set(DefaultRecipientResolver::class)
        ->args([
            param('emagma_sylius_order_withdrawal.notification_email'),
            param('emagma_sylius_order_withdrawal.prefer_channel_contact_email'),
        ])
    ;
    $services->alias(RecipientResolverInterface::class, DefaultRecipientResolver::class);

    $services->alias(WithdrawalRequestContextResolverInterface::class, WithdrawalRequestContextResolver::class);

    $services->set(WithdrawalRequestEmailer::class)
        ->public()
        ->args([
            service('sylius.email_sender'),
            service(WithdrawalRequestContextResolverInterface::class),
        ])
    ;
    $services->alias(WithdrawalRequestEmailerInterface::class, WithdrawalRequestEmailer::class);

    $services->set(ChainWithdrawalRequestNotifier::class)
        ->public()
        ->args([
            tagged_iterator('emagma_sylius_order_withdrawal.notifier'),
            service('logger')->nullOnInvalid(),
        ])
    ;
    $services->alias(WithdrawalRequestNotifierAggregatorInterface::class, ChainWithdrawalRequestNotifier::class);

    $services->set(WithdrawalRequestType::class)
        ->autowire(false)
        ->autoconfigure(false)
        ->args([
            param('emagma_sylius_order_withdrawal.model.withdrawal_request.class'),
            param('emagma_sylius_order_withdrawal.form.type.withdrawal_request.validation_groups'),
        ])
        ->tag('form.type')
    ;

    $services->set(WithdrawalRequestListener::class)
        ->args([
            service(WithdrawalRequestEmailerInterface::class),
            service(WithdrawalRequestNotifierAggregatorInterface::class),
            service('event_dispatcher'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            param('emagma_sylius_order_withdrawal.send_acknowledgement'),
            service('logger')->nullOnInvalid(),
            service('clock')->nullOnInvalid(),
        ])
    ;
};
