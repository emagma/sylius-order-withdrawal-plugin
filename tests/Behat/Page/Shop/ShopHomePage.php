<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop;

use Sylius\Behat\Page\SymfonyPage;

final class ShopHomePage extends SymfonyPage implements ShopHomePageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_homepage';
    }

    public function followWithdrawalRequestLink(): void
    {
        $this->getElement('withdrawal_request_link')->click();
    }

    protected function getDefinedElements(): array
    {
        return [
            'withdrawal_request_link' => '#emagma-footer-withdrawal-link',
        ];
    }
}
