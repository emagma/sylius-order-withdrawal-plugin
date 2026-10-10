<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main', method: 'addMenuItems')]
final class AdminMenuListener
{
    public function addMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $customers = $menu->getChild('customers') ?? $menu->addChild('customers');

        $customers
            ->addChild('emagma_withdrawal_requests', [
                'route' => 'emagma_sylius_order_withdrawal_admin_withdrawal_request_index',
            ])
            ->setLabel('emagma_sylius_order_withdrawal.admin.menu')
            ->setLabelAttribute('icon', 'tabler:arrow-back-up')
        ;
    }
}
