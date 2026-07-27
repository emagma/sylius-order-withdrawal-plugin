<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop\RequestWithdrawalPageInterface;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop\ShopHomePageInterface;
use Webmozart\Assert\Assert;

final readonly class AccessingWithdrawalFormContext implements Context
{
    public function __construct(
        private ShopHomePageInterface $homePage,
        private RequestWithdrawalPageInterface $requestWithdrawalPage,
    ) {
    }

    #[Given('I am browsing the shop')]
    public function iAmBrowsingTheShop(): void
    {
        $this->homePage->open(['_locale' => 'en_US']);
    }

    #[When('I follow the withdrawal request link in the footer')]
    public function iFollowTheWithdrawalRequestLinkInTheFooter(): void
    {
        $this->homePage->followWithdrawalRequestLink();
    }

    #[Then('I should be on the withdrawal request form')]
    public function iShouldBeOnTheWithdrawalRequestForm(): void
    {
        Assert::true($this->requestWithdrawalPage->isOpen(['_locale' => 'en_US']));
    }
}
