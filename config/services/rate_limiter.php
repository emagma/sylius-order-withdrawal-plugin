<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestRateLimitListener;

/*
 * Loaded only when rate_limiter_service names a limiter, which the extension aliases to
 * emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(WithdrawalRequestRateLimitListener::class)
            ->args([
                service('emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request'),
                'emagma_sylius_order_withdrawal_shop_request',
            ])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 8])
    ;
};
