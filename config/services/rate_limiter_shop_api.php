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

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('emagma_sylius_order_withdrawal.rate_limit_listener.shop_api', WithdrawalRequestRateLimitListener::class)
            ->args([
                service('emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request'),
                'emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post',
            ])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 8])
    ;
};
